<?php

declare(strict_types=1);

namespace App\Services\Rates;

use App\Support\WorkStatusFresh;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Observability for the public currencies.xml monitor feed.
 *
 * Alerts (critical logs) when SITE_ONLINE + TECH_OFF would expose empty/177B XML
 * or a sticky hide flag.
 */
final class RatesXmlPublicFeedHealth
{
    public const PUBLISH_LOCK_PATH = '/var/lock/exswaping-xml-publish.lock';

    /**
     * @return array{
     *   path:string,
     *   exists:bool,
     *   bytes:int,
     *   rows:int,
     *   sha256:?string,
     *   gate_hidden:bool,
     *   site_online:bool,
     *   technical_mode:bool,
     *   must_keep_full:bool,
     *   empty_rates_match:bool,
     *   invariant_ok:bool
     * }
     */
    public static function snapshot(?string $path = null): array
    {
        $path ??= public_path('static/exports/currencies.xml');
        $exists = is_file($path);
        $bytes = $exists ? (int) filesize($path) : 0;
        $body = $exists ? (string) file_get_contents($path) : '';
        $rows = $body !== '' ? substr_count($body, '<item>') : 0;
        $sha = $body !== '' ? hash('sha256', $body) : null;
        $emptyMatch = $exists && trim($body) === trim(RatesXmlMonitorGate::EMPTY_RATES_XML);

        $siteOnline = true;
        $tech = false;
        try {
            $siteOnline = ! WorkStatusFresh::isOffline();
        } catch (Throwable) {
            $siteOnline = true;
        }
        try {
            $tech = (int) iEXSetting('is_enabled_technical_mode', 0) === 1;
        } catch (Throwable) {
            $tech = false;
        }

        $mustKeep = RatesXmlMonitorGate::mustKeepFullPublicFeed();
        $hidden = RatesXmlMonitorGate::isHidden();
        $invariantOk = ! $mustKeep
            || ($rows > 0 && ! $emptyMatch && ! $hidden && $bytes > 200);

        return [
            'path' => $path,
            'exists' => $exists,
            'bytes' => $bytes,
            'rows' => $rows,
            'sha256' => $sha,
            'gate_hidden' => $hidden,
            'site_online' => $siteOnline,
            'technical_mode' => $tech,
            'must_keep_full' => $mustKeep,
            'empty_rates_match' => $emptyMatch,
            'invariant_ok' => $invariantOk,
        ];
    }

    /**
     * Emit structured snapshot + critical alert when online invariant is broken.
     */
    public static function assertOnlineInvariant(string $context): void
    {
        $snap = self::snapshot();
        Log::info('rates_xml_public_feed_health', ['context' => $context] + $snap);

        if ($snap['must_keep_full'] && ! $snap['invariant_ok']) {
            Log::critical('rates_xml_online_invariant_violated', [
                'context' => $context,
                'alert' => $snap['empty_rates_match'] || $snap['rows'] === 0
                    ? 'SITE_ONLINE_EMPTY_OR_177B_XML'
                    : 'SITE_ONLINE_STICKY_HIDE_FLAG',
            ] + $snap);
        }
    }
}
