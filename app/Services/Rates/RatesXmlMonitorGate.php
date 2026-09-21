<?php

declare(strict_types=1);

namespace App\Services\Rates;

use App\Support\WorkStatusFresh;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Hide public rate XML from BestChange / monitors during intentional pause.
 *
 * Contract (monitor reliability):
 * - While SITE_ONLINE + TECHNICAL_MODE=OFF, public currencies.xml must stay full
 *   (last-known-good or newly validated). NEVER publish EMPTY_RATES_XML.
 * - Empty <rates/> is allowed only when WorkStatus is offline OR technical mode
 *   is enabled OR the caller passes $forceEmptyPolicy (fail-closed start).
 * - hide()/show() must not leave a sticky flag that diverges from the public file
 *   while the site is online.
 */
final class RatesXmlMonitorGate
{
    public const FLAG_PATH = '/var/lib/exswaping/rates-xml-hidden.flag';

    public const PARSER_OUTPUT_PATH = '/opt/bestchange_parser/data/output.xml';

    /** @internal PHPUnit only — never set in production. */
    private static ?string $flagPathOverride = null;

    public static function flagPath(): string
    {
        return self::$flagPathOverride ?? self::FLAG_PATH;
    }

    /**
     * @internal PHPUnit only.
     */
    public static function setFlagPathOverrideForTests(?string $path): void
    {
        self::$flagPathOverride = $path;
    }

    /** Exact monitor-visible empty document (historically ~177 bytes). */
    public const EMPTY_RATES_XML = '<?xml version="1.0"?>'
        . '<rates version="1"'
        . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
        . ' xsi:noNamespaceSchemaLocation="https://docs.bestchange.biz/schema/1.1.xsd">'
        . '</rates>';

    /**
     * @return list<string>
     */
    public static function publicRateXmlPaths(): array
    {
        return [
            public_path('static/exports/currencies.xml'),
            public_path('static/exports/changed-currencies.xml'),
            public_path('static/exports/currenciesbest.xml'),
            public_path('currencies.xml'),
            public_path('currenciesbest.xml'),
        ];
    }

    public static function isHidden(): bool
    {
        return is_file(self::flagPath());
    }

    /**
     * Online + technical mode OFF ⇒ public feed must remain full.
     */
    public static function mustKeepFullPublicFeed(): bool
    {
        try {
            if (WorkStatusFresh::isOffline()) {
                return false;
            }
        } catch (Throwable) {
            // Cannot read work status: fail closed to "keep full" (never empty by accident).
            return true;
        }

        try {
            if ((int) iEXSetting('is_enabled_technical_mode', 0) === 1) {
                return false;
            }
        } catch (Throwable) {
            // Treat unknown tech mode as OFF ⇒ keep full.
        }

        return true;
    }

    /**
     * Empty monitor feed is allowed only for deliberate offline / tech / force policy.
     */
    public static function mayPublishEmptyFeed(bool $forceEmptyPolicy = false): bool
    {
        if ($forceEmptyPolicy) {
            return true;
        }

        return ! self::mustKeepFullPublicFeed();
    }

    /**
     * @param  bool  $forceEmptyPolicy  Intentional fail-closed / maintenance override.
     *                                  Still refuses when false and site must keep full feed.
     */
    public static function hide(string $reason = 'work_is_offline', bool $forceEmptyPolicy = false): void
    {
        if (! self::mayPublishEmptyFeed($forceEmptyPolicy)) {
            Log::critical('rates_xml_monitor_gate_hide_refused_online', [
                'reason' => $reason,
                'force' => $forceEmptyPolicy,
                'site_online' => true,
                'invariant' => 'SITE_ONLINE+TECH_OFF must keep full XML',
            ]);

            return;
        }

        $flag = self::flagPath();
        $dir = dirname($flag);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $payload = json_encode([
            'hidden' => true,
            'reason' => $reason,
            'at' => gmdate('c'),
        ], JSON_UNESCAPED_SLASHES);
        @file_put_contents($flag, $payload === false ? "hidden\n" : $payload."\n");
        @chmod($flag, 0644);

        $publisher = new AtomicPublicXmlPublisher();
        foreach (self::publicRateXmlPaths() as $path) {
            // Never follow a symlink into the live parser dump when clearing.
            if (is_link($path)) {
                @unlink($path);
            }

            if (str_ends_with($path, 'currenciesbest.xml') || str_ends_with($path, 'changed-currencies.xml')) {
                self::writeEmptyFile($path);
                continue;
            }

            $result = $publisher->publish($path, self::EMPTY_RATES_XML, [
                'min_items' => 0,
                'backup' => true,
                'sync_legacy' => str_contains($path, '/static/exports/') && str_ends_with($path, 'currencies.xml'),
            ]);
            if (! $result['published']) {
                Log::error('rates_xml_monitor_gate_clear_failed', ['path' => $path] + $result);
            }
        }

        Cache::forget('exchanger_client:tech_status_v1');
        Log::warning('rates_xml_monitor_gate_hidden', ['reason' => $reason, 'flag' => $flag]);
    }

    public static function show(string $reason = 'work_is_online'): void
    {
        $flag = self::flagPath();
        if (is_file($flag)) {
            $ok = @unlink($flag);
            if (! $ok || is_file($flag)) {
                Log::critical('rates_xml_monitor_gate_show_unlink_failed', [
                    'reason' => $reason,
                    'flag' => $flag,
                    'still_present' => is_file($flag),
                ]);
            }
        }

        self::restoreCurrenciesBestFromParser();

        Cache::forget('exchanger_client:tech_status_v1');
        Log::info('rates_xml_monitor_gate_shown', [
            'reason' => $reason,
            'flag_cleared' => ! is_file($flag),
        ]);
    }

    /**
     * scheme:files rebuilds currencies.xml; currenciesbest is owned by the parser.
     * Copy the latest parser dump back into the public export path on resume.
     */
    public static function restoreCurrenciesBestFromParser(): void
    {
        $src = self::PARSER_OUTPUT_PATH;
        if (! is_file($src) || filesize($src) < 100) {
            Log::warning('rates_xml_monitor_gate_best_restore_skipped', ['src' => $src]);

            return;
        }

        $dest = public_path('static/exports/currenciesbest.xml');
        $tmp = $dest.'.tmp.'.getmypid();
        if (! @copy($src, $tmp)) {
            Log::error('rates_xml_monitor_gate_best_restore_copy_failed', ['src' => $src, 'dest' => $dest]);

            return;
        }
        @chmod($tmp, 0644);
        if (! @rename($tmp, $dest)) {
            @unlink($tmp);
            Log::error('rates_xml_monitor_gate_best_restore_rename_failed', ['dest' => $dest]);

            return;
        }
        @chmod($dest, 0644);

        // Keep public/currenciesbest.xml as a regular file (never symlink to parser).
        $publicBest = public_path('currenciesbest.xml');
        if (is_link($publicBest)) {
            @unlink($publicBest);
        }
        @copy($dest, $publicBest);
        @chmod($publicBest, 0644);

        Log::info('rates_xml_monitor_gate_best_restored', [
            'items' => substr_count((string) file_get_contents($dest), '<item>'),
        ]);
    }

    private static function writeEmptyFile(string $path): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $tmp = $path.'.tmp.'.getmypid();
        if (@file_put_contents($tmp, self::EMPTY_RATES_XML) === false) {
            return;
        }
        @chmod($tmp, 0644);
        @rename($tmp, $path);
        @chmod($path, 0644);
    }
}
