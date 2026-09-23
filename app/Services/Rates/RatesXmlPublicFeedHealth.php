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

    /** Public feed older than this (when online) triggers a freshness warning in logs. */
    public const PUBLISH_AGE_WARN_SEC = 900;

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
     *   invariant_ok:bool,
     *   mtime_unix:?int,
     *   publish_age_sec:?int,
     *   publish_age_sane:bool
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
        $mtime = $exists ? (int) filemtime($path) : null;
        $age = $mtime !== null ? max(0, time() - $mtime) : null;
        $ageSane = $age === null || $age <= self::PUBLISH_AGE_WARN_SEC;

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
            'mtime_unix' => $mtime,
            'publish_age_sec' => $age,
            'publish_age_sane' => $ageSane,
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

        if ($snap['must_keep_full'] && $snap['invariant_ok'] && ! ($snap['publish_age_sane'] ?? true)) {
            Log::warning('rates_xml_publish_age_stale', [
                'context' => $context,
                'alert' => 'SITE_ONLINE_XML_PUBLISH_AGE',
                'publish_age_sec' => $snap['publish_age_sec'] ?? null,
                'warn_sec' => self::PUBLISH_AGE_WARN_SEC,
            ] + $snap);
        }
    }
}
