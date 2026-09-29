<?php

declare(strict_types=1);

namespace App\Services\Rates;

use App\Models\DirectionExchange;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Read-only owner-authority rate drift monitor.
 *
 * Compares each active direction's course_value to the expected final rate
 * from that direction's certified owner model (not a global market cross-check).
 * Also detects PUBLIC_QUOTE_UNAVAILABLE: weak catalog prefilter passes but
 * RateDirectionEligibility quote_allowed=false (customer-visible without rate).
 * Never mutates rates, ownership, profit, or positions.
 */
final class RateAuthorityDriftMonitor
{
    public const SEVERITY_OK = 'OK';

    public const SEVERITY_WATCH = 'WATCH';

    public const SEVERITY_WARNING = 'WARNING';

    public const SEVERITY_CRITICAL = 'CRITICAL';

    /** @var list<int> */
    private const GRAM_MIGRATED_IDS = [
        132, 410, 411, 412, 413, 414, 415, 416, 417, 418, 419, 420, 421, 422, 423, 424, 425, 426,
    ];

    private const WAVE2A_IDS = [2237];

    private const WAVE2D_IDS = [2870, 1482];

    private const KEEP_DERIVED_IDS = [1513];

    private RateConfiguredExpectation $expectation;

    private RateSanityGuard $guard;

    private ?IndependentMarketBaseline $independent;

    /** @var array<int, array<string,mixed>> */
    private array $bcHealthByDirection = [];

    private ?RateDirectionEligibility $publicSurfaceEligibility = null;

    public function __construct(
        ?RateConfiguredExpectation $expectation = null,
        ?RateSanityGuard $guard = null,
        ?IndependentMarketBaseline $independent = null,
    ) {
        $this->expectation = $expectation ?? new RateConfiguredExpectation();
        $this->guard = $guard ?? new RateSanityGuard();
        // Lazy: IndependentMarketBaseline needs the Laravel container; unit tests omit it.
        $this->independent = $independent;
    }

    private function independent(): IndependentMarketBaseline
    {
        return $this->independent ??= app(IndependentMarketBaseline::class);
    }

    /**
     * One-shot preload of BestChange link health for the full active scan.
     * Avoids per-direction SELECT + derived-outlier work (~minutes at 2k rows).
     */
    private function preloadBestChangeHealth(): void
    {
        $this->bcHealthByDirection = [];
        $rows = DB::table('bestchange_directions')
            ->orderByDesc('status')
            ->orderBy('id')
            ->get([
                'id',
                'id_direction_exchange',
                'status',
                'is_error_parser',
                'rate_value',
                'position_num',
                'updated_at',
                'explain_payload',
            ]);
        foreach ($rows as $bc) {
            $dirId = (int) ($bc->id_direction_exchange ?? 0);
            if ($dirId <= 0 || isset($this->bcHealthByDirection[$dirId])) {
                continue;
            }
            $this->bcHealthByDirection[$dirId] = BestChangeMarketBaseHealth::evaluateFromRow(
                $bc,
                $dirId,
                BestChangeMarketBaseHealth::DEFAULT_MAX_AGE_SECONDS,
                withDerivedOutlier: false,
            );
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function bestChangeHealth(int $id): array
    {
        if (isset($this->bcHealthByDirection[$id])) {
            return $this->bcHealthByDirection[$id];
        }
        if ($this->bcHealthByDirection !== []) {
            // Preload ran but no link row — same as evaluate() missing.
            return [
                'healthy' => false,
                'status' => 'missing',
                'reason' => 'link_missing',
                'direction_id' => $id,
                'rate_value' => null,
                'position_num' => null,
                'updated_at' => null,
                'age_seconds' => null,
                'link_status' => null,
                'derived_reference' => null,
                'outlier_vs_derived' => false,
                'outlier_delta_percent' => null,
            ];
        }

        return BestChangeMarketBaseHealth::evaluate($id);
    }

    /**
     * @return array{
     *   generated_at: string,
     *   summary: array<string,int|list<int>>,
     *   ownership_invariants: list<array<string,mixed>>,
     *   families: array<string,array<string,int>>,
     *   directions: list<array<string,mixed>>,
     *   alert_events: list<array<string,mixed>>
     * }
     */
    public function run(bool $persistState = true, bool $writeJsonl = true): array
    {
        $stateDir = storage_path('app/rates/authority-drift');
        if (!is_dir($stateDir)) {
            File::makeDirectory($stateDir, 0755, true);
        }
        $prevPath = $stateDir.'/alert_state.json';
        $prev = is_file($prevPath)
            ? (json_decode((string) file_get_contents($prevPath), true) ?: [])
            : [];
        /** @var array<string,array<string,mixed>> $prevByKey */
        $prevByKey = is_array($prev['open'] ?? null) ? $prev['open'] : [];

        $directions = [];
        $summary = [
            'RATE_TOTAL' => 0,
            'OK_TOTAL' => 0,
            'WATCH_TOTAL' => 0,
            'WARNING_TOTAL' => 0,
            'CRITICAL_TOTAL' => 0,
            'BESTCHANGE_BAD_TOTAL' => 0,
            'DERIVED_BAD_TOTAL' => 0,
            'ZELLE_BAD_TOTAL' => 0,
            'UNKNOWN_TOTAL' => 0,
            'OWNERSHIP_INVARIANT_DEFECT_TOTAL' => 0,
            'PROVIDER_HEALTH_DEFECT_TOTAL' => 0,
            // BESTCHANGE_STALE_TOTAL is an alias of BOOK_STALE only (not parser errors).
            'BESTCHANGE_STALE_TOTAL' => 0,
            'BESTCHANGE_BOOK_STALE_TOTAL' => 0,
            'BESTCHANGE_PARSER_ERROR_TOTAL' => 0,
            'BESTCHANGE_POSITION_INSUFFICIENT_DEPTH_TOTAL' => 0,
            'BESTCHANGE_POSITION_MISSING_TOTAL' => 0,
            'BESTCHANGE_EMPTY_BOOK_TOTAL' => 0,
            'BESTCHANGE_MAPPING_ERROR_TOTAL' => 0,
            'BOOK_AGE_MAX' => 0,
            'BOOK_AGE_GT_1800_TOTAL' => 0,
            'ACTIONABLE_WARNING_TOTAL' => 0,
            // PUBLIC_QUOTE_DEFECT_TOTAL is a legacy umbrella (historically = unavailable).
            // Prefer PUBLIC_QUOTE_UNAVAILABLE_TOTAL vs REAL_PUBLIC_QUOTE_DEFECT_TOTAL.
            'PUBLIC_QUOTE_DEFECT_TOTAL' => 0,
            'PUBLIC_QUOTE_UNAVAILABLE_TOTAL' => 0,
            'REAL_PUBLIC_QUOTE_DEFECT_TOTAL' => 0,
            'ORDER_ECONOMICS_DEFECT_TOTAL' => 0,
            'XML_RATE_DEFECT_TOTAL' => 0,
            'CRITICAL_IDS' => [],
            'WARNING_IDS' => [],
            'WATCH_IDS' => [],
            'ACTIONABLE_WARNING_IDS' => [],
            'OWNERSHIP_COUNT_DEFINITION' => 'ACTIVE_NONDELETED',
            'OWNERSHIP_COUNT_DEFINITION_DOCUMENTED' => 'YES',
            'OWNERSHIP_ACTIVE_NONDELETED' => [
                'BESTCHANGE' => 0,
                'DERIVED' => 0,
                'ZELLE' => 0,
                'MANUAL' => 0,
                'OTHER' => 0,
            ],
        ];
        $families = [];
        $open = [];
        $alertEvents = [];

        $this->preloadBestChangeHealth();
        foreach ($this->bcHealthByDirection as $health) {
            if ((int) ($health['link_status'] ?? 0) !== 1) {
                continue;
            }
            $age = $health['age_seconds'] ?? null;
            if (! is_int($age)) {
                continue;
            }
            if ($age > (int) $summary['BOOK_AGE_MAX']) {
                $summary['BOOK_AGE_MAX'] = $age;
            }
            if ($age > 1800) {
                $summary['BOOK_AGE_GT_1800_TOTAL']++;
            }
        }

        foreach ($this->ownershipInvariantRows() as $inv) {
            $summary['OWNERSHIP_INVARIANT_DEFECT_TOTAL']++;
            $summary['CRITICAL_TOTAL']++;
            $summary['CRITICAL_IDS'][] = (int) $inv['direction_id'];
            $summary['UNKNOWN_TOTAL'] += $inv['root_cause'] === 'UNKNOWN' ? 1 : 0;
            $directions[] = $inv;
            $key = $this->alertKey((int) $inv['direction_id'], (string) $inv['root_cause'], self::SEVERITY_CRITICAL);
            $open[$key] = $inv;
            foreach ($this->dedupeEvents($prevByKey, $key, $inv) as $ev) {
                $alertEvents[] = $ev;
            }
        }

        $query = DB::table('direction_exchange as de')
            ->leftJoin('currencies as c1', 'c1.id', '=', 'de.id_currency1')
            ->leftJoin('currencies as c2', 'c2.id', '=', 'de.id_currency2')
            ->where('de.status', 1)
            ->whereNull('de.deleted_at')
            ->orderBy('de.id')
            ->select([
                'de.id',
                'de.parser_source_name',
                'de.profit',
                'de.floating_fee',
                'de.course_value',
                'de.allow_export',
                'de.is_enabled_exchange',
                'c1.designation_xml as from_code',
                'c2.designation_xml as to_code',
            ]);

        foreach ($query->cursor() as $row) {
            $summary['RATE_TOTAL']++;
            $eval = $this->evaluateDirection($row);
            $eval = $this->applyPublicQuoteUnavailableGate($eval, $row);
            if (($eval['root_cause'] ?? null) === 'PUBLIC_QUOTE_UNAVAILABLE'
                || ! empty($eval['public_quote_unavailable'])
            ) {
                // Expected fail-closed catalog/quote mismatch — not a live API quote break.
                $summary['PUBLIC_QUOTE_UNAVAILABLE_TOTAL']++;
                $summary['PUBLIC_QUOTE_DEFECT_TOTAL']++; // legacy alias (do not treat as REAL_*)
            } elseif (($eval['alert_class'] ?? null) === 'PUBLIC_QUOTE'
                || str_starts_with((string) ($eval['root_cause'] ?? ''), 'PUBLIC_QUOTE_')
            ) {
                $summary['REAL_PUBLIC_QUOTE_DEFECT_TOTAL']++;
                $summary['PUBLIC_QUOTE_DEFECT_TOTAL']++;
            }
            $directions[] = $eval;
            $sev = (string) $eval['severity'];
            $owner = (string) $eval['owner'];
            $summary[$sev.'_TOTAL'] = (int) ($summary[$sev.'_TOTAL'] ?? 0) + 1;
            if ($sev === self::SEVERITY_CRITICAL) {
                $summary['CRITICAL_IDS'][] = (int) $eval['direction_id'];
            } elseif ($sev === self::SEVERITY_WARNING) {
                $summary['WARNING_IDS'][] = (int) $eval['direction_id'];
            } elseif ($sev === self::SEVERITY_WATCH) {
                $summary['WATCH_IDS'][] = (int) $eval['direction_id'];
            }
            if (in_array($sev, [self::SEVERITY_WARNING, self::SEVERITY_CRITICAL], true)) {
                if ($owner === 'BESTCHANGE') {
                    $summary['BESTCHANGE_BAD_TOTAL']++;
                } elseif ($owner === 'DERIVED') {
                    $summary['DERIVED_BAD_TOTAL']++;
                } elseif ($owner === 'ZELLE') {
                    $summary['ZELLE_BAD_TOTAL']++;
                }
            }
            $root = (string) ($eval['root_cause'] ?? '');
            $ownerBucket = $owner;
            if (isset($summary['OWNERSHIP_ACTIVE_NONDELETED'][$ownerBucket])) {
                $summary['OWNERSHIP_ACTIVE_NONDELETED'][$ownerBucket]++;
            } else {
                $summary['OWNERSHIP_ACTIVE_NONDELETED']['OTHER']++;
            }

            // Independent BC diagnostic counters (facts may both fire for one direction).
            if ($owner === 'BESTCHANGE') {
                $providerHit = false;
                if (! empty($eval['book_age_stale'])) {
                    $summary['BESTCHANGE_BOOK_STALE_TOTAL']++;
                    $summary['BESTCHANGE_STALE_TOTAL']++; // alias: book-age only
                    $providerHit = true;
                }
                if (! empty($eval['parser_error_fact']) || in_array($root, [
                    'BESTCHANGE_PARSER_ERROR',
                    'BESTCHANGE_POSITION_INSUFFICIENT_DEPTH',
                ], true)) {
                    $summary['BESTCHANGE_PARSER_ERROR_TOTAL']++;
                    $providerHit = true;
                }
                if ($root === 'BESTCHANGE_POSITION_INSUFFICIENT_DEPTH') {
                    $summary['BESTCHANGE_POSITION_INSUFFICIENT_DEPTH_TOTAL']++;
                } elseif ($root === 'BESTCHANGE_MAPPING_MISMATCH') {
                    $summary['BESTCHANGE_MAPPING_ERROR_TOTAL']++;
                    $providerHit = true;
                } elseif ($root === 'BESTCHANGE_BOOK_THIN') {
                    $summary['BESTCHANGE_EMPTY_BOOK_TOTAL']++;
                    $providerHit = true;
                } elseif ($root === 'BESTCHANGE_POSITION_MISSING') {
                    $summary['BESTCHANGE_POSITION_MISSING_TOTAL']++;
                    $providerHit = true;
                }
                if ($providerHit) {
                    $summary['PROVIDER_HEALTH_DEFECT_TOTAL']++;
                }
            } elseif (str_starts_with($root, 'DERIVED_PROVIDER_') || $root === 'DERIVED_CROSS_RATE_BAD') {
                $summary['PROVIDER_HEALTH_DEFECT_TOTAL']++;
            }
            if ($root === 'UNKNOWN') {
                $summary['UNKNOWN_TOTAL']++;
            }

            $famKey = $owner.'/'.((string) ($eval['pair_family'] ?? 'OTHER'));
            if (!isset($families[$famKey])) {
                $families[$famKey] = ['OK' => 0, 'WATCH' => 0, 'WARNING' => 0, 'CRITICAL' => 0];
            }
            $families[$famKey][$sev] = ($families[$famKey][$sev] ?? 0) + 1;

            // Alert only WARNING/CRITICAL (WATCH stays in counters/JSONL without spam).
            if (in_array($sev, [self::SEVERITY_WARNING, self::SEVERITY_CRITICAL], true)) {
                $key = $this->alertKey((int) $eval['direction_id'], $root !== '' ? $root : 'UNKNOWN', $sev);
                $open[$key] = $eval;
                foreach ($this->dedupeEvents($prevByKey, $key, $eval) as $ev) {
                    $alertEvents[] = $ev;
                }
                if (! empty($eval['public_impact']) || ! empty($eval['order_impact'])) {
                    $summary['ACTIONABLE_WARNING_TOTAL']++;
                    $summary['ACTIONABLE_WARNING_IDS'][] = (int) $eval['direction_id'];
                }
            }
            // BESTCHANGE_BOOK_STALE WATCH rows are tracked via systemic global key only.
        }

        // Document both ownership populations (do not equate them).
        $summary['OWNERSHIP_STATUS1_INCLUDING_SOFT_DELETED'] = $this->ownershipStatus1IncludingSoftDeleted();

        // Collapse systemic BestChange book staleness BEFORE recovery so global open key exists.
        $collapsed = $this->collapseSystemicBestChangeStaleAlerts($alertEvents, $summary, $prevByKey);
        $alertEvents = $collapsed['events'];
        foreach ($collapsed['open_extra'] as $k => $row) {
            $open[$k] = $row;
        }
        // When systemic collapse is active, drop per-direction BOOK_STALE keys so recovery
        // does not flood one RECOVERED per direction when the book heals.
        if (($collapsed['systemic'] ?? false) === true) {
            foreach (array_keys($open) as $k) {
                if ($k === ($collapsed['global_key'] ?? '')) {
                    continue;
                }
                $row = $open[$k];
                if (($row['owner'] ?? '') === 'BESTCHANGE'
                    && ($row['root_cause'] ?? '') === 'BESTCHANGE_BOOK_STALE'
                ) {
                    unset($open[$k]);
                }
            }
        }

        // Recoveries: previously open, now absent
        foreach ($prevByKey as $key => $old) {
            if (!isset($open[$key])) {
                $alertEvents[] = [
                    'type' => 'RECOVERED',
                    'direction_id' => $old['direction_id'] ?? null,
                    'pair' => $old['pair'] ?? null,
                    'owner' => $old['owner'] ?? null,
                    'previous_root_cause' => $old['root_cause'] ?? null,
                    'previous_severity' => $old['severity'] ?? null,
                    'first_bad_at' => $old['first_bad_at'] ?? null,
                    'recovered_at' => now()->toIso8601String(),
                    'final_diff_percent' => $old['diff_percent'] ?? null,
                    'action' => 'OBSERVE',
                    'alert_class' => $old['alert_class'] ?? 'AUTHORITY_DATA_STALE',
                ];
            }
        }

        if ($writeJsonl) {
            $this->appendJsonl($directions, $alertEvents);
        }
        if ($persistState) {
            $doc = [
                'updated_at' => now()->toIso8601String(),
                'summary' => $summary,
                'open' => $open,
            ];
            $partial = $prevPath.'.partial';
            file_put_contents($partial, json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");
            rename($partial, $prevPath);
        }

        foreach ($alertEvents as $ev) {
            if (($ev['type'] ?? '') === 'RECOVERED') {
                Log::info('rates.authority_drift_recovered', $ev);
            } else {
                Log::warning('rates.authority_drift_alert', $ev);
            }
        }

        // Technical Telegram is opt-in + dedicated OPS chat only (never order/traffic).
        try {
            $opsStats = app(\App\Services\Ops\TelegramOpsAlertRouter::class)
                ->deliverRateAlertEvents($alertEvents);
            $summary['TELEGRAM_OPS_DELIVERY'] = $opsStats;
            $summary['OPS_TELEGRAM_CHAT_ID_CONFIGURED'] = app(\App\Services\Ops\TelegramOpsAlertRouter::class)->opsChatConfigured() ? 'YES' : 'NO';
            $summary['RATE_TELEGRAM_ALERTS_ENABLED'] = app(\App\Services\Ops\TelegramOpsAlertRouter::class)->rateTelegramAlertsEnabled() ? 'YES' : 'NO';
        } catch (\Throwable $e) {
            Log::warning('telegram_ops_alert_router_failed', [
                'exception_class' => $e::class,
            ]);
            $summary['TELEGRAM_OPS_DELIVERY'] = [
                'attempted' => 0,
                'sent' => 0,
                'skipped' => count($alertEvents),
                'blocked_reason' => 'ROUTER_EXCEPTION',
            ];
            $summary['OPS_TELEGRAM_CHAT_ID_CONFIGURED'] = 'NO';
            $summary['RATE_TELEGRAM_ALERTS_ENABLED'] = 'NO';
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'summary' => $summary,
            'ownership_invariants' => array_values(array_filter(
                $directions,
                static fn ($d) => ($d['root_cause'] ?? '') === 'OWNER_CHANGED_UNEXPECTEDLY'
                    || str_starts_with((string) ($d['root_cause'] ?? ''), 'OWNER_')
                    || str_contains((string) ($d['root_cause'] ?? ''), 'REGISTRY')
                    || str_contains((string) ($d['root_cause'] ?? ''), 'BLOCK_BESTCHANGE')
            )),
            'families' => $families,
            'directions' => array_values(array_filter(
                $directions,
                static fn ($d) => ($d['severity'] ?? self::SEVERITY_OK) !== self::SEVERITY_OK
            )),
            'alert_events' => $alertEvents,
            'monitor' => [
                'mutates_rates' => false,
                'duplicate_safe' => true,
                'reference_model' => 'owner_authority',
            ],
        ];
    }

    /**
     * @param  object  $row
     * @return array<string,mixed>
     */
    private function evaluateDirection(object $row): array
    {
        $id = (int) $row->id;
        $from = strtoupper((string) ($row->from_code ?? ''));
        $to = strtoupper((string) ($row->to_code ?? ''));
        $pair = ($from !== '' && $to !== '') ? "{$from}→{$to}" : "id:{$id}";
        $owner = $this->normalizeOwner((string) ($row->parser_source_name ?? ''));
        $course = $this->guard->normalize((string) ($row->course_value ?? ''));
        $profit = (string) ($row->profit ?? '0');
        $allowExport = (int) ($row->allow_export ?? 0);
        $exchangeOn = (int) ($row->is_enabled_exchange ?? 0) === 1;
        // Soft-lane (severity downgrade): exchange form disabled. XML export-off alone is normal.
        $softLane = ! $exchangeOn;
        $exportOff = $allowExport === 0;

        $base = [
            'direction_id' => $id,
            'pair' => $pair,
            'pair_family' => $this->pairFamily($from, $to),
            'owner' => $owner,
            'parser_source_name' => (string) ($row->parser_source_name ?? ''),
            'profit' => $profit,
            'current_final' => $course,
            'expected_final' => null,
            'diff_percent' => null,
            'severity' => self::SEVERITY_OK,
            'root_cause' => null,
            'first_bad_stage' => null,
            'reference_model' => null,
            'reference_source' => null,
            'reference_age_sec' => null,
            'position' => null,
            'public_impact' => false,
            'order_impact' => false,
            'xml_impact' => false,
            'export_off' => $exportOff,
            'exchange_on' => $exchangeOn,
            'soft_lane' => $softLane,
            'paid_unsettled_orders' => 0,
            'action' => 'OBSERVE',
        ];

        if ($course === null) {
            return $this->finalize(array_merge($base, [
                'severity' => self::SEVERITY_CRITICAL,
                'root_cause' => 'STORED_COURSE_STALE',
                'first_bad_stage' => 'course_value_invalid',
                'action' => 'INVESTIGATE',
            ]), $softLane);
        }

        return match ($owner) {
            'BESTCHANGE' => $this->evaluateBestChange($base, $id, $course, $profit, $softLane),
            'DERIVED' => $this->evaluateDerived($base, $id, $from, $to, $course, $profit, $softLane),
            'ZELLE' => $this->evaluateZelle($base, $id, $course, $row, $softLane),
            'MANUAL' => $this->finalize(array_merge($base, [
                'reference_model' => 'MANUAL_NO_EXTERNAL_BENCHMARK',
                'root_cause' => 'MANUAL_EXPECTED',
                'severity' => self::SEVERITY_OK,
                'action' => 'OBSERVE',
            ]), $softLane),
            default => $this->evaluateOther($base, $from, $to, $course, $profit, $softLane),
        };
    }

    /**
     * @param  array<string,mixed>  $base
     * @return array<string,mixed>
     */
    private function evaluateBestChange(array $base, int $id, string $course, string $profit, bool $exportOff): array
    {
        $base['reference_model'] = 'BESTCHANGE_POSITION_PLUS_PROFIT';
        $health = $this->bestChangeHealth($id);
        $base['position'] = $health['position_num'] ?? null;
        $base['reference_age_sec'] = $health['age_seconds'] ?? null;
        $base['reference_source'] = 'bestchange_directions.rate_value';
        $base['book_age_stale'] = (bool) ($health['book_age_stale'] ?? false);
        $base['parser_error_fact'] = (bool) ($health['is_error_parser'] ?? false);
        $base['book_depth'] = $health['book_depth'] ?? null;
        $base['explain_reason'] = $health['explain_reason'] ?? null;

        if (!($health['healthy'] ?? false)) {
            $reason = (string) ($health['reason'] ?? 'unknown');
            // First meaningful failure stage — do not collapse parser errors into book-age stale.
            $root = match ($reason) {
                'stale' => 'BESTCHANGE_BOOK_STALE',
                'link_missing' => 'BESTCHANGE_MAPPING_MISMATCH',
                'link_inactive' => 'BC_LINK_DISABLED',
                'non_positive_rate', 'invalid' => 'BESTCHANGE_BOOK_THIN',
                'position_insufficient_depth' => 'BESTCHANGE_POSITION_INSUFFICIENT_DEPTH',
                'parser_error' => 'BESTCHANGE_PARSER_ERROR',
                default => 'BESTCHANGE_PARSER_ERROR',
            };
            $stage = match ($reason) {
                'link_missing', 'link_inactive' => 'bestchange_link',
                'stale' => 'bestchange_book_age',
                'position_insufficient_depth' => 'bestchange_position_depth',
                'parser_error' => 'bestchange_parser_error',
                default => 'bestchange_book',
            };

            // Still compute expected from last BC base when available (diagnostic only).
            $bcBase = $this->guard->normalize((string) ($health['rate_value'] ?? ''));
            $expected = null;
            $diff = null;
            if ($bcBase !== null) {
                $analysis = $this->expectation->analyze(
                    baseline: $bcBase,
                    actual: $course,
                    profitPercent: $profit,
                );
                $expected = $analysis['expected_final_rate'] ?? null;
                $diff = $analysis['unexplained_deviation'] ?? null;
            }

            // Fail-closed / non-orderable rows: operational diagnostic, not customer CRITICAL.
            $sev = $exportOff ? self::SEVERITY_WATCH : self::SEVERITY_WARNING;

            return $this->finalize(array_merge($base, [
                'severity' => $sev,
                'root_cause' => $root,
                'first_bad_stage' => $stage,
                'expected_final' => $expected,
                'diff_percent' => $diff,
                'action' => $exportOff ? 'OBSERVE' : 'INVESTIGATE',
            ]), $exportOff); // $exportOff here is softLane from caller
        }

        $bcBase = $this->guard->normalize((string) ($health['rate_value'] ?? ''));
        if ($bcBase === null) {
            return $this->finalize(array_merge($base, [
                'severity' => self::SEVERITY_WARNING,
                'root_cause' => 'BESTCHANGE_BOOK_THIN',
                'first_bad_stage' => 'bestchange_rate_value',
                'action' => 'INVESTIGATE',
            ]), $exportOff);
        }

        $analysis = $this->expectation->analyze(
            baseline: $bcBase,
            actual: $course,
            profitPercent: $profit,
        );
        $expected = $analysis['expected_final_rate'];
        $diff = $analysis['unexplained_deviation'];
        $base['expected_final'] = $expected;
        $base['diff_percent'] = $diff;

        if ($diff === null) {
            return $this->finalize(array_merge($base, [
                'severity' => self::SEVERITY_WATCH,
                'root_cause' => 'UNKNOWN',
                'first_bad_stage' => 'expectation_analyze',
                'action' => 'INVESTIGATE',
            ]), $exportOff);
        }

        $sev = $this->severityFromDiff((float) $diff);
        if ($sev === self::SEVERITY_OK) {
            return $this->finalize(array_merge($base, [
                'severity' => self::SEVERITY_OK,
                'root_cause' => null,
                'first_bad_stage' => null,
                'action' => 'OBSERVE',
            ]), $exportOff);
        }

        return $this->finalize(array_merge($base, [
            'severity' => $sev,
            'root_cause' => abs((float) $diff) > 0.01 ? 'PROFIT_APPLICATION_MISMATCH' : 'STORED_COURSE_STALE',
            'first_bad_stage' => 'course_value_vs_bc_base_plus_profit',
            'action' => $sev === self::SEVERITY_CRITICAL ? 'DEDICATED_PRICING_REMEDIATION' : 'INVESTIGATE',
        ]), $exportOff);
    }

    /**
     * @param  array<string,mixed>  $base
     * @return array<string,mixed>
     */
    private function evaluateDerived(
        array $base,
        int $id,
        string $from,
        string $to,
        string $course,
        string $profit,
        bool $exportOff,
    ): array {
        $base['reference_model'] = 'DERIVED_INDEPENDENT_CROSSCHECK';
        if ($this->isIntentionalDerivedException($id, $from, $to)) {
            // Authority is DERIVED by policy; independent BC-like cross-check is not a defect.
            return $this->finalize(array_merge($base, [
                'severity' => self::SEVERITY_OK,
                'root_cause' => 'INTENTIONAL_MARKET_BASIS',
                'first_bad_stage' => null,
                'reference_source' => 'keep_derived_or_payeer_policy',
                'action' => 'OBSERVE',
            ]), $exportOff);
        }

        $ind = $this->independentBaseline($from, $to);
        if ($ind === null || !isset($ind['rate'])) {
            // No certified independent leg (exotic fiat/OTC) — not an authority defect.
            return $this->finalize(array_merge($base, [
                'severity' => self::SEVERITY_OK,
                'root_cause' => 'INTENTIONAL_MARKET_BASIS',
                'first_bad_stage' => null,
                'reference_source' => 'independent_baseline_unavailable',
                'action' => 'OBSERVE',
            ]), $exportOff);
        }

        $base['reference_source'] = (string) ($ind['source'] ?? 'independent');
        $analysis = $this->expectation->analyze(
            baseline: (string) $ind['rate'],
            actual: $course,
            profitPercent: $profit,
        );
        $base['expected_final'] = $analysis['expected_final_rate'];
        $base['diff_percent'] = $analysis['unexplained_deviation'];
        $diff = $analysis['unexplained_deviation'];
        if ($diff === null) {
            return $this->finalize(array_merge($base, [
                'severity' => self::SEVERITY_WATCH,
                'root_cause' => 'UNKNOWN',
                'first_bad_stage' => 'expectation_analyze',
                'action' => 'INVESTIGATE',
            ]), $exportOff);
        }

        $abs = abs((float) $diff);
        // Soft band for DERIVED OTC vs spot: WATCH to 8%, WARNING 8–15%, CRITICAL >15%
        $sev = self::SEVERITY_OK;
        if ($abs >= 15.0) {
            $sev = self::SEVERITY_CRITICAL;
        } elseif ($abs >= 8.0) {
            $sev = self::SEVERITY_WARNING;
        } elseif ($abs >= 3.0) {
            $sev = self::SEVERITY_WATCH;
        }

        if ($sev === self::SEVERITY_OK) {
            return $this->finalize(array_merge($base, [
                'severity' => self::SEVERITY_OK,
                'root_cause' => null,
                'action' => 'OBSERVE',
            ]), $exportOff);
        }

        $root = $abs >= 8.0 ? 'DERIVED_CROSS_RATE_BAD' : 'INTENTIONAL_MARKET_BASIS';
        if ($sev === self::SEVERITY_WATCH && $root === 'INTENTIONAL_MARKET_BASIS') {
            // Spot-vs-OTC noise — do not escalate.
            return $this->finalize(array_merge($base, [
                'severity' => self::SEVERITY_WATCH,
                'root_cause' => 'INTENTIONAL_MARKET_BASIS',
                'first_bad_stage' => 'independent_vs_derived_basis',
                'action' => 'OBSERVE',
            ]), $exportOff);
        }

        return $this->finalize(array_merge($base, [
            'severity' => $sev,
            'root_cause' => $root,
            'first_bad_stage' => 'derived_vs_independent_crosscheck',
            'action' => $sev === self::SEVERITY_CRITICAL ? 'QUARANTINE_EXISTING_POLICY' : 'INVESTIGATE',
        ]), $exportOff);
    }

    /**
     * @param  array<string,mixed>  $base
     * @return array<string,mixed>
     */
    private function evaluateZelle(array $base, int $id, string $course, object $row, bool $exportOff): array
    {
        $base['reference_model'] = 'ZELLE_USDT_BENCHMARK_FEES';
        // Zelle authority writes course_value; floating/form fees carry margin.
        // Drift vs self is not meaningful without recomputing peer — flag only invalid/non-positive.
        if (bccomp($course, '0', 18) <= 0) {
            return $this->finalize(array_merge($base, [
                'severity' => self::SEVERITY_CRITICAL,
                'root_cause' => 'ZELLE_AUTHORITY_STALE',
                'first_bad_stage' => 'course_value',
                'action' => 'INVESTIGATE',
            ]), $exportOff);
        }

        return $this->finalize(array_merge($base, [
            'expected_final' => $course,
            'diff_percent' => 0.0,
            'severity' => self::SEVERITY_OK,
            'root_cause' => null,
            'reference_source' => 'zelle_authority_self_consistent',
            'action' => 'OBSERVE',
        ]), $exportOff);
    }

    /**
     * @param  array<string,mixed>  $base
     * @return array<string,mixed>
     */
    private function evaluateOther(array $base, string $from, string $to, string $course, string $profit, bool $exportOff): array
    {
        $base['reference_model'] = 'OTHER_DECLARED_AUTHORITY';
        $ind = $this->independentBaseline($from, $to);
        if ($ind === null || !isset($ind['rate'])) {
            return $this->finalize(array_merge($base, [
                'severity' => self::SEVERITY_OK,
                'root_cause' => null,
                'action' => 'OBSERVE',
            ]), $exportOff);
        }
        $analysis = $this->expectation->analyze((string) $ind['rate'], $course, $profit);
        $base['expected_final'] = $analysis['expected_final_rate'];
        $base['diff_percent'] = $analysis['unexplained_deviation'];
        $diff = $analysis['unexplained_deviation'];
        if ($diff === null) {
            return $this->finalize($base, $exportOff);
        }
        $sev = $this->severityFromDiff((float) $diff);

        return $this->finalize(array_merge($base, [
            'severity' => $sev,
            'root_cause' => $sev === self::SEVERITY_OK ? null : 'DERIVED_CROSS_RATE_BAD',
            'first_bad_stage' => $sev === self::SEVERITY_OK ? null : 'other_vs_independent',
            'action' => $sev === self::SEVERITY_OK ? 'OBSERVE' : 'OWNER_REVIEW',
        ]), $exportOff);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function ownershipInvariantRows(): array
    {
        $rows = [];
        $check = function (int $id, string $expectOwner, string $label) use (&$rows): void {
            $de = DB::table('direction_exchange')->where('id', $id)->first(['id', 'parser_source_name', 'status']);
            if ($de === null) {
                $rows[] = [
                    'direction_id' => $id,
                    'pair' => $label,
                    'pair_family' => 'INVARIANT',
                    'owner' => 'MISSING',
                    'severity' => self::SEVERITY_CRITICAL,
                    'root_cause' => 'OWNER_CHANGED_UNEXPECTEDLY',
                    'first_bad_stage' => 'direction_missing',
                    'current_final' => null,
                    'expected_final' => null,
                    'diff_percent' => null,
                    'public_impact' => true,
                    'order_impact' => true,
                    'xml_impact' => true,
                    'action' => 'DEDICATED_PRICING_REMEDIATION',
                    'reference_model' => 'OWNERSHIP_INVARIANT',
                ];

                return;
            }
            $owner = $this->normalizeOwner((string) ($de->parser_source_name ?? ''));
            if ($owner !== $expectOwner) {
                $rows[] = [
                    'direction_id' => $id,
                    'pair' => $label,
                    'pair_family' => 'INVARIANT',
                    'owner' => $owner,
                    'severity' => self::SEVERITY_CRITICAL,
                    'root_cause' => 'OWNER_CHANGED_UNEXPECTEDLY',
                    'first_bad_stage' => 'parser_source_name',
                    'current_final' => null,
                    'expected_final' => $expectOwner,
                    'diff_percent' => null,
                    'public_impact' => true,
                    'order_impact' => true,
                    'xml_impact' => true,
                    'action' => 'DEDICATED_PRICING_REMEDIATION',
                    'reference_model' => 'OWNERSHIP_INVARIANT',
                    'expected_owner' => $expectOwner,
                    'actual_owner' => $owner,
                ];
            }
        };

        foreach (self::GRAM_MIGRATED_IDS as $id) {
            $check($id, 'BESTCHANGE', "GRAM#{$id}");
        }
        foreach (self::WAVE2A_IDS as $id) {
            $check($id, 'BESTCHANGE', "WAVE2A#{$id}");
        }
        foreach (self::WAVE2D_IDS as $id) {
            $check($id, 'BESTCHANGE', "WAVE2D#{$id}");
        }
        foreach (self::KEEP_DERIVED_IDS as $id) {
            $check($id, 'DERIVED', "KEEP_DERIVED#{$id}");
        }

        // Registry re-entry: GRAM/wave ids must not appear in derived registry resource.
        $regPath = base_path('resources/rates/derived-market-baseline-directions.json');
        if (is_file($regPath)) {
            $reg = json_decode((string) file_get_contents($regPath), true);
            $dirs = is_array($reg['directions'] ?? null) ? $reg['directions'] : [];
            foreach (array_merge(self::GRAM_MIGRATED_IDS, self::WAVE2A_IDS, self::WAVE2D_IDS) as $id) {
                if (isset($dirs[(string) $id]) || isset($dirs[$id])) {
                    $rows[] = [
                        'direction_id' => $id,
                        'pair' => "REGISTRY#{$id}",
                        'pair_family' => 'INVARIANT',
                        'owner' => 'REGISTRY',
                        'severity' => self::SEVERITY_CRITICAL,
                        'root_cause' => 'REGISTRY_REENTRY',
                        'first_bad_stage' => 'derived-market-baseline-directions.json',
                        'current_final' => null,
                        'expected_final' => null,
                        'diff_percent' => null,
                        'public_impact' => false,
                        'order_impact' => false,
                        'xml_impact' => false,
                        'action' => 'DEDICATED_PRICING_REMEDIATION',
                        'reference_model' => 'OWNERSHIP_INVARIANT',
                    ];
                }
            }
        }

        return $rows;
    }

    private function severityFromDiff(float $diffPercent): string
    {
        $abs = abs($diffPercent);
        if ($abs < 1.0) {
            return self::SEVERITY_OK;
        }
        if ($abs < 3.0) {
            return self::SEVERITY_WATCH;
        }
        if ($abs < 5.0) {
            return self::SEVERITY_WARNING;
        }

        return self::SEVERITY_CRITICAL;
    }

    /**
     * @param  array<string,mixed>  $row
     * @return array<string,mixed>
     */
    private function finalize(array $row, bool $softLane): array
    {
        $sev = (string) ($row['severity'] ?? self::SEVERITY_OK);
        if ($softLane) {
            if ($sev === self::SEVERITY_CRITICAL) {
                $sev = self::SEVERITY_WARNING;
            } elseif ($sev === self::SEVERITY_WARNING) {
                $sev = self::SEVERITY_WATCH;
            }
            $row['severity'] = $sev;
        }

        $exchangeOn = (bool) ($row['exchange_on'] ?? false);
        $xmlOn = ! (bool) ($row['export_off'] ?? true);
        $impact = in_array($sev, [self::SEVERITY_WARNING, self::SEVERITY_CRITICAL], true);
        $row['order_impact'] = $impact && $exchangeOn;
        $row['xml_impact'] = $impact && $xmlOn;
        $row['public_impact'] = $row['order_impact'] || $row['xml_impact'];
        $row['alert_class'] = $this->alertClassForRoot((string) ($row['root_cause'] ?? ''));

        return $row;
    }

    private function alertClassForRoot(string $root): string
    {
        return match (true) {
            str_contains($root, 'BOOK_STALE'), str_contains($root, 'AUTHORITY_STALE'), str_contains($root, 'STORED_COURSE_STALE'),
            str_contains($root, 'PROVIDER_STALE') => 'AUTHORITY_DATA_STALE',
            str_contains($root, 'POSITION_INSUFFICIENT'), str_contains($root, 'PARSER_ERROR'),
            str_contains($root, 'BOOK_THIN'), str_contains($root, 'POSITION_MISSING') => 'PROVIDER_UNAVAILABLE',
            str_contains($root, 'PROVIDER_BREAK'), str_contains($root, 'PROVIDER_UNAVAILABLE'),
            str_contains($root, 'MAPPING_MISMATCH'), str_contains($root, 'LINK_DISABLED') => 'PROVIDER_UNAVAILABLE',
            str_contains($root, 'OWNER_CHANGED'), str_contains($root, 'REGISTRY_REENTRY') => 'OWNERSHIP_INVARIANT',
            $root === 'PUBLIC_QUOTE_UNAVAILABLE' => 'PUBLIC_QUOTE_UNAVAILABLE',
            str_contains($root, 'PUBLIC_QUOTE') => 'PUBLIC_QUOTE_DIVERGENCE',
            str_contains($root, 'ORDER_QUOTE') => 'ORDER_QUOTE_DIVERGENCE',
            str_contains($root, 'XML_') => 'XML_DIVERGENCE',
            $root === '' || $root === 'UNKNOWN' => 'UNKNOWN',
            default => 'AUTHORITY_PRICE_DRIFT',
        };
    }

    /**
     * Detect catalog/deep-link advertisement without a usable public quote.
     *
     * Weak prefilter (positive course, not duplicate) + quote_allowed=false
     * is exactly the customer defect certification found on the six RubFamily
     * quarantine directions. Distinct from BOOK_STALE / PARSER_ERROR / drift.
     *
     * @param  array<string,mixed>  $eval
     * @return array<string,mixed>
     */
    private function applyPublicQuoteUnavailableGate(array $eval, object $row): array
    {
        $id = (int) ($row->id ?? 0);
        $course = $row->course_value ?? null;
        if (! CanonicalDirectionEligibility::passesPublicCatalogPrefilter($id, $course)) {
            return $eval;
        }

        try {
            $direction = DirectionExchange::query()->with(['currency1', 'currency2'])->find($id);
            if ($direction === null) {
                return $eval;
            }
            $surface = $this->publicSurfaceEligibility()->evaluateDirection($direction);
        } catch (Throwable) {
            return $eval;
        }

        $quoteAllowed = ! empty($surface['quote_allowed']);
        $orderAllowed = ! empty($surface['order_allowed']);
        $eval['quote_allowed'] = $quoteAllowed;
        $eval['order_allowed'] = $orderAllowed;
        $eval['eligibility_classification'] = $surface['classification'] ?? null;

        if ($quoteAllowed) {
            return $eval;
        }

        // Customer-impacting: still weakly catalog-eligible / deep-linkable, no quote.
        $eval['public_quote_unavailable'] = true;
        $eval['prior_root_cause'] = $eval['root_cause'] ?? null;
        $eval['root_cause'] = 'PUBLIC_QUOTE_UNAVAILABLE';
        $eval['first_bad_stage'] = 'public_quote_eligibility';
        $eval['severity'] = self::SEVERITY_WARNING;
        $eval['public_impact'] = true;
        // Soft-lane (is_enabled_exchange=0) must NOT hide this — selector/catalog can still advertise.
        $eval['order_impact'] = $orderAllowed;
        $eval['action'] = 'FIX_PUBLIC_AVAILABILITY_GATE';
        $eval['alert_class'] = 'PUBLIC_QUOTE_UNAVAILABLE';
        $eval['customer_impact'] = 'VISIBLE_WITHOUT_RATE';

        return $eval;
    }

    private function publicSurfaceEligibility(): RateDirectionEligibility
    {
        return $this->publicSurfaceEligibility ??= RateDirectionEligibility::make();
    }

    /**
     * Non-canonical companion count: status=1 including soft-deleted rows.
     * Do not compare to ACTIVE_NONDELETED as if identical.
     *
     * @return array{BESTCHANGE:int,DERIVED:int,ZELLE:int,MANUAL:int,OTHER:int}
     */
    private function ownershipStatus1IncludingSoftDeleted(): array
    {
        $out = ['BESTCHANGE' => 0, 'DERIVED' => 0, 'ZELLE' => 0, 'MANUAL' => 0, 'OTHER' => 0];
        $rows = DB::table('direction_exchange')
            ->where('status', 1)
            ->selectRaw('parser_source_name, COUNT(*) AS c')
            ->groupBy('parser_source_name')
            ->pluck('c', 'parser_source_name');
        foreach ($rows as $src => $c) {
            $bucket = $this->normalizeOwner((string) $src);
            if (! isset($out[$bucket])) {
                $bucket = 'OTHER';
            }
            $out[$bucket] += (int) $c;
        }

        return $out;
    }

    private function normalizeOwner(string $raw): string
    {
        $s = trim($raw);
        if ($s === 'BestChange') {
            return 'BESTCHANGE';
        }
        if ($s === 'DERIVED_MARKET_BASELINE') {
            return 'DERIVED';
        }
        if ($s === 'ZELLE_USDTTRC20_BENCHMARK') {
            return 'ZELLE';
        }
        if ($s === 'Ручной курс' || strcasecmp($s, 'Manual') === 0) {
            return 'MANUAL';
        }

        return $s !== '' ? 'OTHER' : 'UNKNOWN';
    }

    private function pairFamily(string $from, string $to): string
    {
        if (str_contains($to, 'RUB') || str_contains($from, 'RUB')) {
            return 'RUB';
        }
        if (str_contains($to, 'USD') || str_contains($from, 'USD')) {
            return 'USD';
        }
        if (str_starts_with($from, 'PR') || str_starts_with($to, 'PR')) {
            return 'PAYEER';
        }
        if (str_contains($from, 'ZELLE') || str_contains($to, 'ZELLE')) {
            return 'ZELLE';
        }
        if (str_contains($from, 'GRAM') || str_contains($to, 'GRAM')) {
            return 'GRAM';
        }

        return 'CRYPTO_OTHER';
    }

    private function isIntentionalDerivedException(int $id, string $from, string $to): bool
    {
        if (in_array($id, self::KEEP_DERIVED_IDS, true)) {
            return true;
        }
        foreach (['PRRUB', 'PRUSD', 'PREUR'] as $code) {
            if (str_contains($from, $code) || str_contains($to, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{rate:string,source:string,path?:string}|null
     */
    private function independentBaseline(string $from, string $to): ?array
    {
        $fromAsset = IndependentMarketBaseline::assetFromCode($from);
        $toAsset = IndependentMarketBaseline::assetFromCode($to);

        if (str_contains($to, 'RUB')) {
            if ($fromAsset === 'USDT' || $fromAsset === 'USDC') {
                $q = $this->independent()->quote('USDRUB');

                return $q ? ['rate' => $q['rate'], 'source' => $q['source'], 'path' => 'stable_to_rub'] : null;
            }
            if ($fromAsset !== null) {
                $q = $this->independent()->cryptoRub($fromAsset);

                return $q ? ['rate' => $q['rate'], 'source' => $q['source'], 'path' => 'crypto_to_rub'] : null;
            }
        }

        if ($fromAsset !== null && $toAsset !== null) {
            $q = $this->independent()->cryptoViaUsdt($fromAsset, $toAsset);

            return $q ? ['rate' => $q['rate'], 'source' => $q['source'], 'path' => 'crypto_via_usdt'] : null;
        }

        return null;
    }

    /**
     * When a large share of BestChange rows share BOOK_STALE, emit one global alert
     * and suppress per-direction OPEN spam for that root (state/JSONL still retain all).
     *
     * @param  list<array<string,mixed>>  $alertEvents
     * @param  array<string,int|list<int>>  $summary
     * @return list<array<string,mixed>>
     */
    /**
     * When a large share of BestChange rows share BOOK_STALE, emit one global alert
     * and suppress per-direction OPEN spam for that root (state/JSONL still retain all).
     *
     * @param  list<array<string,mixed>>  $alertEvents
     * @param  array<string,int|list<int>>  $summary
     * @param  array<string,array<string,mixed>>  $prevByKey
     * @return array{events:list<array<string,mixed>>,open_extra:array<string,array<string,mixed>>}
     */
    private function collapseSystemicBestChangeStaleAlerts(array $alertEvents, array $summary, array $prevByKey): array
    {
        $staleOpens = [];
        $other = [];
        foreach ($alertEvents as $ev) {
            if (($ev['type'] ?? '') === 'OPEN'
                && ($ev['owner'] ?? '') === 'BESTCHANGE'
                && ($ev['root_cause'] ?? '') === 'BESTCHANGE_BOOK_STALE'
            ) {
                $staleOpens[] = $ev;
            } else {
                $other[] = $ev;
            }
        }

        $staleTotal = (int) ($summary['BESTCHANGE_STALE_TOTAL'] ?? 0);
        $bcBad = (int) ($summary['BESTCHANGE_BAD_TOTAL'] ?? 0);
        if (count($staleOpens) < 25 && $staleTotal < 25 && $bcBad < 25) {
            return ['events' => $alertEvents, 'open_extra' => [], 'systemic' => false, 'global_key' => null];
        }

        $sev = $bcBad > 0 ? self::SEVERITY_WARNING : self::SEVERITY_WATCH;
        $globalKey = $this->alertKey(0, 'BESTCHANGE_BOOK_STALE', $sev);
        $sampleIds = array_values(array_unique(array_map(
            static fn ($e) => (int) ($e['direction_id'] ?? 0),
            array_slice($staleOpens, 0, 20)
        )));
        if ($sampleIds === [] && $staleTotal > 0) {
            $sampleIds = array_slice(array_map('intval', (array) ($summary['WATCH_IDS'] ?? [])), 0, 20);
        }

        $global = [
            'type' => 'OPEN',
            'severity' => $sev,
            'direction_id' => 0,
            'pair' => 'BESTCHANGE/*',
            'owner' => 'BESTCHANGE',
            'root_cause' => 'BESTCHANGE_BOOK_STALE',
            'first_bad_stage' => 'bestchange_book_age_global',
            'current_final' => null,
            'expected_final' => null,
            'diff_percent' => null,
            'public_impact' => $bcBad > 0,
            'order_impact' => $bcBad > 0,
            'xml_impact' => false,
            'action' => 'INVESTIGATE',
            'systemic' => true,
            'alert_class' => 'AUTHORITY_DATA_STALE',
            'affected_count' => max(count($staleOpens), $staleTotal, $bcBad),
            'sample_ids' => $sampleIds,
            'note' => 'Systemic BestChange book staleness; per-direction alerts collapsed. NO AUTO RATE CHANGE.',
        ];

        $events = $other;
        if (! isset($prevByKey[$globalKey])) {
            $events[] = $global;
        }

        return [
            'events' => $events,
            'open_extra' => [$globalKey => $global],
            'systemic' => true,
            'global_key' => $globalKey,
        ];
    }

    private function alertKey(int $id, string $root, string $sev): string
    {
        return $id.'|'.$root.'|'.$sev;
    }

    /**
     * @param  array<string,array<string,mixed>>  $prevByKey
     * @param  array<string,mixed>  $row
     * @return list<array<string,mixed>>
     */
    private function dedupeEvents(array $prevByKey, string $key, array $row): array
    {
        if (isset($prevByKey[$key])) {
            return [];
        }
        // severity increase / root change: also alert if same id had different key
        $id = (int) ($row['direction_id'] ?? 0);
        foreach ($prevByKey as $pkey => $old) {
            if ((int) ($old['direction_id'] ?? 0) !== $id) {
                continue;
            }
            $oldSev = (string) ($old['severity'] ?? '');
            $newSev = (string) ($row['severity'] ?? '');
            $rank = [self::SEVERITY_OK => 0, self::SEVERITY_WATCH => 1, self::SEVERITY_WARNING => 2, self::SEVERITY_CRITICAL => 3];
            $oldRoot = (string) ($old['root_cause'] ?? '');
            $newRoot = (string) ($row['root_cause'] ?? '');
            if ($oldRoot === $newRoot && ($rank[$newSev] ?? 0) <= ($rank[$oldSev] ?? 0)) {
                return [];
            }
        }

        return [[
            'type' => 'OPEN',
            'timestamp' => now()->toIso8601String(),
            'direction_id' => $row['direction_id'] ?? null,
            'pair' => $row['pair'] ?? null,
            'owner' => $row['owner'] ?? null,
            'severity' => $row['severity'] ?? null,
            'current_final' => $row['current_final'] ?? null,
            'expected_final' => $row['expected_final'] ?? null,
            'diff_percent' => $row['diff_percent'] ?? null,
            'root_cause' => $row['root_cause'] ?? null,
            'first_bad_stage' => $row['first_bad_stage'] ?? null,
            'public_impact' => $row['public_impact'] ?? false,
            'order_impact' => $row['order_impact'] ?? false,
            'xml_impact' => $row['xml_impact'] ?? false,
            'action' => $row['action'] ?? 'INVESTIGATE',
            'alert_key' => $key,
        ]];
    }

    /**
     * @param  list<array<string,mixed>>  $directions
     * @param  list<array<string,mixed>>  $events
     */
    private function appendJsonl(array $directions, array $events): void
    {
        $path = storage_path('logs/rates_authority_drift.jsonl');
        $fh = fopen($path, 'ab');
        if ($fh === false) {
            return;
        }
        $ts = now()->toIso8601String();
        foreach ($directions as $d) {
            if (($d['severity'] ?? self::SEVERITY_OK) === self::SEVERITY_OK) {
                continue;
            }
            fwrite($fh, json_encode(['timestamp' => $ts, 'kind' => 'direction'] + $d, JSON_UNESCAPED_UNICODE)."\n");
        }
        foreach ($events as $e) {
            fwrite($fh, json_encode(['timestamp' => $ts, 'kind' => 'alert_event'] + $e, JSON_UNESCAPED_UNICODE)."\n");
        }
        fclose($fh);

        // Evidence mirror (ops path) — best effort
        $ops = '/var/lib/exswaping-owned/pricing/authority-drift';
        if (is_dir($ops) || @mkdir($ops, 0755, true)) {
            $opsPath = $ops.'/rates_authority_drift.jsonl';
            @file_put_contents($opsPath, '', FILE_APPEND);
            $fh2 = @fopen($opsPath, 'ab');
            if ($fh2) {
                foreach ($events as $e) {
                    fwrite($fh2, json_encode(['timestamp' => $ts, 'kind' => 'alert_event'] + $e, JSON_UNESCAPED_UNICODE)."\n");
                }
                fclose($fh2);
            }
        }
    }

    public function formatOperatorAlert(array $event): string
    {
        if (($event['type'] ?? '') === 'RECOVERED') {
            return implode("\n", [
                '✅ RATE RECOVERED',
                '',
                'ID: '.($event['direction_id'] ?? ''),
                'PAIR: '.($event['pair'] ?? ''),
                'OWNER: '.($event['owner'] ?? ''),
                'PREVIOUS ROOT CAUSE: '.($event['previous_root_cause'] ?? ''),
                'RECOVERED AT: '.($event['recovered_at'] ?? ''),
            ]);
        }

        $root = (string) ($event['root_cause'] ?? 'UNKNOWN');
        if ($root === 'BESTCHANGE_BOOK_STALE' || ($event['alert_class'] ?? '') === 'AUTHORITY_DATA_STALE' && str_contains($root, 'STALE') && ! str_contains($root, 'PARSER')) {
            return implode("\n", [
                '⏱ AUTHORITY DATA STALE — '.($event['severity'] ?? 'WARNING'),
                '',
                'ID: '.($event['direction_id'] ?? ''),
                'PAIR: '.($event['pair'] ?? ''),
                'OWNER: '.($event['owner'] ?? ''),
                '',
                'LAST SUCCESSFUL INGEST AGE (sec): '.($event['reference_age_sec'] ?? $event['reference_age'] ?? 'n/a'),
                'FRESHNESS THRESHOLD: 1800s (warning 1200s / target ~900s)',
                'REFERENCE: '.($event['reference_source'] ?? 'bestchange_directions.updated_at'),
                '',
                'PUBLIC IMPACT: '.(($event['public_impact'] ?? false) ? 'YES' : 'NO'),
                'ORDER IMPACT: '.(($event['order_impact'] ?? false) ? 'YES' : 'NO'),
                'XML IMPACT: '.(($event['xml_impact'] ?? false) ? 'YES' : 'NO'),
                '',
                'ACTION: '.($event['action'] ?? 'INVESTIGATE'),
                'NO AUTO RATE CHANGE.',
            ]);
        }

        if (in_array($root, [
            'BESTCHANGE_PARSER_ERROR',
            'BESTCHANGE_POSITION_INSUFFICIENT_DEPTH',
            'BESTCHANGE_BOOK_THIN',
            'BESTCHANGE_MAPPING_MISMATCH',
        ], true)) {
            return implode("\n", [
                '⚠ BESTCHANGE BOOK UNUSABLE — '.($event['severity'] ?? 'WARNING'),
                '',
                'ID: '.($event['direction_id'] ?? ''),
                'PAIR: '.($event['pair'] ?? ''),
                'OWNER: '.($event['owner'] ?? ''),
                '',
                'CONFIGURED POSITION: '.($event['position'] ?? 'n/a'),
                'VALID DEPTH: '.($event['book_depth'] ?? 'n/a'),
                'REASON: '.($event['explain_reason'] ?? $root),
                'ROOT CAUSE: '.$root,
                'FIRST BAD STAGE: '.($event['first_bad_stage'] ?? 'bestchange_parser'),
                'BOOK AGE STALE: '.((! empty($event['book_age_stale'])) ? 'YES' : 'NO'),
                '',
                'PUBLIC IMPACT: '.(($event['public_impact'] ?? false) ? 'YES' : 'NO'),
                'ORDER IMPACT: '.(($event['order_impact'] ?? false) ? 'YES' : 'NO'),
                'XML IMPACT: '.(($event['xml_impact'] ?? false) ? 'YES' : 'NO'),
                '',
                'SAFE ACTION: '.($event['action'] ?? 'OBSERVE'),
                'NO AUTO RATE CHANGE. NO AUTO POSITION CHANGE.',
            ]);
        }

        return implode("\n", [
            '🚨 RATE DRIFT — '.($event['severity'] ?? 'WARNING'),
            '',
            'ID: '.($event['direction_id'] ?? ''),
            'PAIR: '.($event['pair'] ?? ''),
            'OWNER: '.($event['owner'] ?? ''),
            '',
            'CURRENT: '.($event['current_final'] ?? ''),
            'EXPECTED: '.($event['expected_final'] ?? ''),
            'DIFF: '.((array_key_exists('diff_percent', $event) && $event['diff_percent'] !== null)
                ? (sprintf('%+.2f%%', (float) $event['diff_percent']))
                : 'n/a'),
            'REFERENCE: '.($event['reference_source'] ?? 'n/a'),
            '',
            'ROOT CAUSE: '.$root,
            'FIRST BAD STAGE: '.($event['first_bad_stage'] ?? 'unknown'),
            '',
            'PUBLIC IMPACT: '.(($event['public_impact'] ?? false) ? 'YES' : 'NO'),
            'ORDER IMPACT: '.(($event['order_impact'] ?? false) ? 'YES' : 'NO'),
            'XML IMPACT: '.(($event['xml_impact'] ?? false) ? 'YES' : 'NO'),
            '',
            'ACTION: '.($event['action'] ?? 'INVESTIGATE'),
            'NO AUTO RATE CHANGE.',
        ]);
    }
}
