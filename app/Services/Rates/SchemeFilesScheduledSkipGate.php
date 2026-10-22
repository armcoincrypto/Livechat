<?php

declare(strict_types=1);

namespace App\Services\Rates;

use App\Models\DirectionExchange;
use App\Models\ExportRatesFile;
use App\Support\WorkStatusFresh;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Scheduled-only skip for scheme:files when the complete export input fingerprint
 * matches the last successful full export and published outputs remain valid.
 *
 * Fail-open: any uncertainty returns shouldSkip=false (full export).
 * Never skips post-bc, force-publish, or --skip-export invocations.
 * Flag default OFF: SCHEME_FILES_SCHEDULED_SKIP_UNCHANGED.
 */
final class SchemeFilesScheduledSkipGate
{
    public const FLAG = 'SCHEME_FILES_SCHEDULED_SKIP_UNCHANGED';

    public const FP_VERSION = 1;

    public const META_CACHE_KEY = 'scheme_files.scheduled_skip.meta.v1';

    public const MIN_XML_ITEMS = 1;

    /**
     * @param  array{
     *   invocation?: string,
     *   force_publish?: bool,
     *   skip_export?: bool,
     *   xml_path?: string|null
     * }  $context
     * @return array{
     *   skip: bool,
     *   reason: string,
     *   fingerprint: ?string,
     *   enabled: bool
     * }
     */
    public function evaluate(array $context = []): array
    {
        $enabled = self::flagEnabled();
        $invocation = strtolower(trim((string) ($context['invocation'] ?? self::processInvocation())));
        if ($invocation === '') {
            $invocation = 'scheduled';
        }

        $offline = false;
        $offlineFailed = false;
        try {
            $offline = WorkStatusFresh::isOffline();
        } catch (Throwable) {
            $offlineFailed = true;
        }

        $monitorHidden = false;
        $monitorFailed = false;
        try {
            $monitorHidden = RatesXmlMonitorGate::isHidden();
        } catch (Throwable) {
            $monitorFailed = true;
        }

        $meta = $this->readMeta();
        $xmlPath = (string) ($context['xml_path'] ?? public_path('static/exports/currencies.xml'));
        $outputFail = null;
        if (is_array($meta)) {
            $outputFail = $this->validatePublishedOutputs($xmlPath, $meta);
        }

        $fp = null;
        $fpFailed = false;
        if ($enabled
            && $invocation === 'scheduled'
            && empty($context['force_publish'])
            && empty($context['skip_export'])
            && ! $offline
            && ! $offlineFailed
            && ! $monitorHidden
            && ! $monitorFailed
            && is_array($meta)
            && ($meta['last_result'] ?? '') === 'success'
            && (int) ($meta['fp_version'] ?? 0) === self::FP_VERSION
            && $outputFail === null
        ) {
            try {
                $fp = $this->computeInputFingerprint();
            } catch (Throwable $e) {
                Log::warning('scheme_files_scheduled_skip_fp_failed', [
                    'message' => $e->getMessage(),
                ]);
                $fpFailed = true;
            }
        }

        $cachesOk = true;
        $cacheFailReason = null;
        if ($fp !== null && ! $fpFailed) {
            foreach (ExchangeRatesCacheInvalidator::KEYS as $key) {
                try {
                    $cached = Cache::store('redis')->get($key);
                } catch (Throwable) {
                    $cachesOk = false;
                    $cacheFailReason = 'cache_read_failed:'.$key;
                    break;
                }
                if (! is_array($cached) || $cached === [] || empty($cached['paymentSystems'] ?? null)) {
                    $cachesOk = false;
                    $cacheFailReason = 'cache_missing_or_invalid:'.$key;
                    break;
                }
            }
        }

        return self::decideFromFacts([
            'enabled' => $enabled,
            'invocation' => $invocation,
            'force_publish' => ! empty($context['force_publish']),
            'skip_export' => ! empty($context['skip_export']),
            'offline' => $offline,
            'offline_failed' => $offlineFailed,
            'monitor_hidden' => $monitorHidden,
            'monitor_failed' => $monitorFailed,
            'meta' => $meta,
            'output_fail' => $outputFail,
            'fingerprint' => $fp,
            'fingerprint_failed' => $fpFailed,
            'caches_ok' => $cachesOk,
            'cache_fail_reason' => $cacheFailReason,
            'fp_version' => self::FP_VERSION,
        ]);
    }

    /**
     * Pure decision matrix for unit tests (fail-open).
     *
     * @param  array<string, mixed>  $facts
     * @return array{skip: bool, reason: string, fingerprint: ?string, enabled: bool}
     */
    public static function decideFromFacts(array $facts): array
    {
        $enabled = (bool) ($facts['enabled'] ?? false);
        $fp = isset($facts['fingerprint']) && is_string($facts['fingerprint'])
            ? $facts['fingerprint']
            : null;

        if (! $enabled) {
            return self::decision(false, 'flag_off', null, false);
        }

        $invocation = (string) ($facts['invocation'] ?? 'scheduled');
        if ($invocation === 'post-bc') {
            return self::decision(false, 'post_bc_mandatory', null, true);
        }
        if ($invocation !== 'scheduled') {
            return self::decision(false, 'invocation_not_scheduled:'.$invocation, null, true);
        }
        if (! empty($facts['force_publish'])) {
            return self::decision(false, 'force_publish', null, true);
        }
        if (! empty($facts['skip_export'])) {
            return self::decision(false, 'skip_export_option', null, true);
        }
        if (! empty($facts['offline_failed'])) {
            return self::decision(false, 'offline_check_failed', null, true);
        }
        if (! empty($facts['offline'])) {
            return self::decision(false, 'site_offline', null, true);
        }
        if (! empty($facts['monitor_failed'])) {
            return self::decision(false, 'monitor_check_failed', null, true);
        }
        if (! empty($facts['monitor_hidden'])) {
            return self::decision(false, 'monitor_hidden', null, true);
        }

        $meta = $facts['meta'] ?? null;
        if (! is_array($meta)) {
            return self::decision(false, 'no_prior_success_meta', null, true);
        }
        if (($meta['last_result'] ?? '') !== 'success') {
            return self::decision(false, 'prior_export_not_success', null, true);
        }
        $expectFpVer = (int) ($facts['fp_version'] ?? self::FP_VERSION);
        if ((int) ($meta['fp_version'] ?? 0) !== $expectFpVer) {
            return self::decision(false, 'fp_version_mismatch', null, true);
        }
        if (! empty($facts['output_fail']) && is_string($facts['output_fail'])) {
            return self::decision(false, $facts['output_fail'], null, true);
        }
        if (! empty($facts['fingerprint_failed'])) {
            return self::decision(false, 'fingerprint_failed', null, true);
        }
        if ($fp === null || $fp === '') {
            return self::decision(false, 'fingerprint_failed', null, true);
        }
        if (! hash_equals((string) ($meta['fingerprint'] ?? ''), $fp)) {
            return self::decision(false, 'fingerprint_changed', $fp, true);
        }
        if (array_key_exists('caches_ok', $facts) && $facts['caches_ok'] === false) {
            $reason = is_string($facts['cache_fail_reason'] ?? null)
                ? (string) $facts['cache_fail_reason']
                : 'cache_missing_or_invalid';

            return self::decision(false, $reason, $fp, true);
        }

        return self::decision(true, 'unchanged_complete_inputs', $fp, true);
    }

    /**
     * @return array{skip: bool, reason: string, fingerprint: ?string, enabled: bool}
     */
    private static function decision(bool $skip, string $reason, ?string $fp, bool $enabled): array
    {
        return [
            'skip' => $skip,
            'reason' => $reason,
            'fingerprint' => $fp,
            'enabled' => $enabled,
        ];
    }

    public function recordSuccess(string $fingerprint, array $outputMeta = []): void
    {
        if ($fingerprint === '') {
            $this->recordFailure('empty_fingerprint_on_success');

            return;
        }

        $payload = [
            'fp_version' => self::FP_VERSION,
            'fingerprint' => $fingerprint,
            'last_result' => 'success',
            'recorded_at' => gmdate('c'),
            'xml_sha256' => $outputMeta['xml_sha256'] ?? null,
            'xml_items' => $outputMeta['xml_items'] ?? null,
            'xml_bytes' => $outputMeta['xml_bytes'] ?? null,
            'dr_snapshot_id' => $outputMeta['dr_snapshot_id'] ?? Cache::get('dr_snapshot_id'),
        ];

        try {
            Cache::forever(self::META_CACHE_KEY, $payload);
        } catch (Throwable $e) {
            Log::warning('scheme_files_scheduled_skip_meta_write_failed', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function recordFailure(string $reason = 'export_failed'): void
    {
        try {
            $meta = $this->readMeta() ?? [];
            $meta['last_result'] = 'failure';
            $meta['failure_reason'] = $reason;
            $meta['failed_at'] = gmdate('c');
            $meta['fp_version'] = self::FP_VERSION;
            Cache::forever(self::META_CACHE_KEY, $meta);
        } catch (Throwable $e) {
            Log::warning('scheme_files_scheduled_skip_failure_meta_failed', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Complete input fingerprint covering XML + RU/EN catalog rebuild inputs.
     * Intentionally excludes cs.created_at (always changes) and wall-clock except
     * time-window open-state for directions with is_enabled_exchange=1.
     */
    public function computeInputFingerprint(): string
    {
        $h = hash_init('sha256');
        hash_update($h, 'v'.self::FP_VERSION."\n");

        $this->hashSettings($h);
        $this->hashExportFileConfigs($h);
        $this->hashCurrenciesAndReserves($h);
        $this->hashDirections($h);
        $this->hashDirectionChildren($h);
        $this->hashTimeWindowOpenMask($h);
        $this->hashPublicationState($h);

        return hash_final($h);
    }

    public static function flagEnabled(): bool
    {
        return self::envBool(self::FLAG, false);
    }

    public static function processInvocation(): string
    {
        $g = getenv('EXSWAPING_SCHEME_FILES_INVOCATION');

        return is_string($g) && $g !== '' ? $g : 'scheduled';
    }

    private static function envBool(string $key, bool $default): bool
    {
        $raw = null;
        $g = getenv($key);
        if ($g !== false) {
            $raw = $g;
        } elseif (function_exists('env')) {
            $raw = env($key, null);
        }
        if ($raw === null) {
            return $default;
        }

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }


    /**
     * @return array<string, mixed>|null
     */
    private function readMeta(): ?array
    {
        try {
            $meta = Cache::get(self::META_CACHE_KEY);
        } catch (Throwable) {
            return null;
        }

        return is_array($meta) ? $meta : null;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function validatePublishedOutputs(string $xmlPath, array $meta): ?string
    {
        if (! is_file($xmlPath) || ! is_readable($xmlPath)) {
            return 'xml_missing';
        }
        $bytes = filesize($xmlPath);
        if ($bytes === false || $bytes < 32) {
            return 'xml_too_small';
        }
        $xml = (string) file_get_contents($xmlPath);
        if ($xml === '' || ! str_contains($xml, '<rates')) {
            return 'xml_corrupt';
        }
        $items = substr_count($xml, '<item>');
        if ($items < self::MIN_XML_ITEMS) {
            return 'xml_empty_items';
        }

        $sha = hash('sha256', $xml);
        if (! empty($meta['xml_sha256']) && ! hash_equals((string) $meta['xml_sha256'], $sha)) {
            // Published file drifted vs last success record without our rebuild —
            // do not trust skip (external rewrite / partial publish).
            return 'xml_sha_drift';
        }

        return null;
    }

    /** @param resource $h */
    private function hashSettings($h): void
    {
        $keys = [
            'app_multilanguage_locale',
            'ids_currencies_reserves_home_view',
            'max_decimal_places',
            'is_operator_online',
            'default_exchange_direction_mode',
        ];
        foreach ($keys as $key) {
            $val = '';
            try {
                $val = (string) iEXSetting($key, '');
            } catch (Throwable) {
                $val = '?';
            }
            hash_update($h, "setting:{$key}={$val}\n");
        }
        hash_update($h, 'app_version='.(string) config('iexexchanger.version.current', '')."\n");
        hash_update($h, 'offline='.(WorkStatusFresh::isOffline() ? '1' : '0')."\n");
        hash_update($h, 'monitor_hidden='.(RatesXmlMonitorGate::isHidden() ? '1' : '0')."\n");
    }

    /** @param resource $h */
    private function hashExportFileConfigs($h): void
    {
        if (! Schema::hasTable('export_rates_files') && ! Schema::hasTable('export_rates_file')) {
            // Table name varies; try model.
        }
        try {
            foreach (ExportRatesFile::query()->orderBy('id')->cursor() as $cfg) {
                hash_update($h, implode('|', [
                    'erf',
                    (string) $cfg->id,
                    (string) ($cfg->status ?? ''),
                    (string) ($cfg->filename ?? ''),
                    (string) ($cfg->type_file ?? ''),
                    json_encode($cfg->ids_excluded_directions ?? [], JSON_UNESCAPED_UNICODE),
                    (string) ($cfg->in_type_fromfee ?? ''),
                    (string) ($cfg->in_type_tofee ?? ''),
                ])."\n");
            }
        } catch (Throwable $e) {
            throw new \RuntimeException('export_rates_file_hash_failed: '.$e->getMessage(), 0, $e);
        }
    }

    /** @param resource $h */
    private function hashCurrenciesAndReserves($h): void
    {
        try {
            $curCols = ['id', 'status', 'designation_xml', 'number_format', 'id_code_currency'];
            $select = [];
            foreach ($curCols as $c) {
                if (Schema::hasColumn('currencies', $c)) {
                    $select[] = 'c.'.$c;
                }
            }
            if ($select === [] || ! Schema::hasColumn('currencies', 'id')) {
                throw new \RuntimeException('currencies columns missing');
            }
            if (Schema::hasTable('reserves') && Schema::hasColumn('reserves', 'summa')) {
                $select[] = 'r.summa';
            }
            $q = DB::table('currencies as c')->orderBy('c.id');
            if (Schema::hasTable('reserves') && Schema::hasColumn('reserves', 'id_currency')) {
                $q->leftJoin('reserves as r', 'r.id_currency', '=', 'c.id');
            }
            foreach ($q->select($select)->cursor() as $row) {
                $parts = ['cur'];
                foreach ($select as $aliased) {
                    $name = str_contains($aliased, '.') ? substr($aliased, strpos($aliased, '.') + 1) : $aliased;
                    $parts[] = (string) ($row->{$name} ?? '');
                }
                hash_update($h, implode('|', $parts)."\n");
            }
        } catch (Throwable $e) {
            throw new \RuntimeException('currency_hash_failed: '.$e->getMessage(), 0, $e);
        }
    }

    /** @param resource $h */
    private function hashDirections($h): void
    {
        $columns = [
            'id',
            'id_currency1',
            'id_currency2',
            'status',
            'is_error_rate',
            'course_value',
            'exchange_rate',
            'profit',
            'allow_export',
            'parser_source_name',
            'sorting_2',
            'is_enabled_exchange',
            'from_on_time',
            'to_on_time',
            'is_hidden_not_locale',
            'languages',
            'is_hidden_not_device',
            'device',
            'type_reserve',
            'direction_reserve',
            'floating_fee',
            'fix_fee',
            'is_type_rate',
            'min_price1',
            'max_price1',
            'min_price2',
            'max_price2',
            'oth_comm_percent',
            'oth_comm_currency',
            'pay_comm_percent',
            'pay_comm_currency',
            'export_label_param',
            'hidden_export_label_param',
            'label_floating_minutes',
            'label_floating_percent',
            'label_delay',
            'tech_name',
            'deleted_at',
        ];

        try {
            $existing = array_values(array_filter(
                $columns,
                static fn (string $c): bool => Schema::hasColumn('direction_exchange', $c)
            ));
            if ($existing === [] || ! in_array('id', $existing, true)) {
                throw new \RuntimeException('direction_exchange columns missing');
            }

            DirectionExchange::query()
                ->withTrashed()
                ->orderBy('id')
                ->select($existing)
                ->chunkById(500, function ($rows) use ($h, $existing): void {
                    foreach ($rows as $row) {
                        $parts = ['de'];
                        foreach ($existing as $col) {
                            $parts[] = (string) ($row->{$col} ?? '');
                        }
                        hash_update($h, implode('|', $parts)."\n");
                    }
                });
        } catch (Throwable $e) {
            throw new \RuntimeException('direction_hash_failed: '.$e->getMessage(), 0, $e);
        }
    }

    /** @param resource $h */
    private function hashDirectionChildren($h): void
    {
        $tables = [
            'direction_exchange_cities' => [
                'id', 'id_direction_exchange', 'city_id', 'status', 'add_comm',
                'profit', 'profit_s', 'min_price', 'max_price', 'param', 'sorting',
            ],
            'direction_exchange_percentage_amounts' => [
                'id', 'id_direction_exchange', 'from_amount', 'to_amount', 'percentage',
            ],
            'direction_exchange_modes' => ['id', 'name', 'status'],
            'directions_has_modes' => ['id', 'direction_exchange_id', 'direction_exchange_mode_id'],
        ];

        $resolved = [
            'direction_exchange_cities' => $this->firstExistingTable([
                'direction_exchange_cities',
                'direction_exchange_city',
            ]),
            'direction_exchange_percentage_amounts' => $this->firstExistingTable([
                'direction_exchange_percent_amount',
                'direction_exchange_percentage_amounts',
                'direction_exchange_percentage_amount',
            ]),
            'direction_exchange_modes' => $this->firstExistingTable([
                'direction_exchange_modes',
                'direction_exchange_mode',
            ]),
            'directions_has_modes' => $this->firstExistingTable([
                'directions_has_modes',
            ]),
        ];

        foreach ($resolved as $label => $table) {
            if ($table === null) {
                hash_update($h, "child:{$label}=absent\n");

                continue;
            }
            $want = $tables[$label];
            // Pivot may use morph column names — probe extras.
            if ($label === 'directions_has_modes') {
                $want = array_values(array_unique(array_merge(
                    $want,
                    Schema::getColumnListing($table)
                )));
            }
            $cols = array_values(array_filter(
                $want,
                static fn (string $c): bool => Schema::hasColumn($table, $c)
            ));
            if ($cols === []) {
                hash_update($h, "child:{$label}=nocols\n");

                continue;
            }
            $order = in_array('id', $cols, true) ? 'id' : $cols[0];
            foreach (DB::table($table)->orderBy($order)->select($cols)->cursor() as $row) {
                $parts = [$label];
                foreach ($cols as $c) {
                    $parts[] = (string) ($row->{$c} ?? '');
                }
                hash_update($h, implode('|', $parts)."\n");
            }
        }
    }

    /** @param resource $h */
    private function hashTimeWindowOpenMask($h): void
    {
        $nowHm = gmdate('H:i');
        // Use app timezone if available for consistency with configureDirectionRates.
        try {
            $nowHm = \Carbon\Carbon::now()->format('H:i');
        } catch (Throwable) {
        }

        hash_update($h, "clock_hm={$nowHm}\n");

        if (! Schema::hasColumn('direction_exchange', 'is_enabled_exchange')) {
            return;
        }

        try {
            $q = DirectionExchange::query()
                ->where('status', 1)
                ->where('is_enabled_exchange', 1)
                ->orderBy('id')
                ->select(['id', 'from_on_time', 'to_on_time']);
            foreach ($q->cursor() as $row) {
                $from = (string) ($row->from_on_time ?? '');
                $to = (string) ($row->to_on_time ?? '');
                $open = '1';
                if ($from !== '' && $to !== '') {
                    $open = ($nowHm < $from || $nowHm > $to) ? '0' : '1';
                }
                hash_update($h, "tw|{$row->id}|{$from}|{$to}|{$open}\n");
            }
        } catch (Throwable $e) {
            throw new \RuntimeException('time_window_hash_failed: '.$e->getMessage(), 0, $e);
        }
    }

    /** @param resource $h */
    private function hashPublicationState($h): void
    {
        hash_update($h, 'pub_lock='.RatesXmlPublicFeedHealth::PUBLISH_LOCK_PATH."\n");
    }

    /**
     * @param  list<string>  $candidates
     */
    private function firstExistingTable(array $candidates): ?string
    {
        foreach ($candidates as $t) {
            try {
                if (Schema::hasTable($t)) {
                    return $t;
                }
            } catch (Throwable) {
            }
        }

        return null;
    }
}
