<?php

declare(strict_types=1);

namespace App\Services\Rates;

use App\Models\DirectionExchange;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * OPTIONAL USDT→classic-RUB absolute commercial sync.
 *
 * Default production ownership is MANUAL_PROFIT: admin «Прибыль» is durable.
 * Absolute AUTO_TARGET is opt-in only when BOTH are true:
 *   - policy intentional_commercial.commercial_mode = AUTO_TARGET
 *   - env EXSWAPING_ALLOW_USDT_RUB_AUTO_TARGET=1
 *
 * Never writes the frozen legacy bootstrap −15.661082.
 */
final class UsdtRubCommercialTargetSyncService
{
    public const WRITER = 'policy:UsdtRubCommercialTargetSync';

    public const LOCK_KEY = 'iex:rates:usdt_rub_commercial_sync';

    public const LOCK_TTL_SECONDS = 120;

    public const LAST_SYNC_CACHE_KEY = 'exswaping:rates:usdt_rub_commercial_last_sync_v1';

    public const AUTO_TARGET_ENV = 'EXSWAPING_ALLOW_USDT_RUB_AUTO_TARGET';

    /** Persist profit to 6 decimal places (matches historical commercial precision). */
    public const PROFIT_SCALE = 6;

    /** Rewrite when |effective − target| exceeds this (RUB per 1 USDT). */
    public const EFFECTIVE_GAP_TOLERANCE = '0.05';

    /** Absolute sanity ceiling on |profit| regardless of family hard max. */
    public const PROFIT_ABS_HARD_SANITY = '25';

    /** Frozen legacy bootstrap — health must keep count at 0. */
    public const LEGACY_MAGIC_PROFIT = '-15.661082';

    /** @var list<string> */
    public const DEFAULT_FROM_CODES = [
        'USDTTRC20',
        'USDTERC20',
        'USDTBEP20',
        'USDTTON',
        'USDTSOL',
    ];

    public function __construct(
        private readonly RubFamilyPremiumPolicy $policy = new RubFamilyPremiumPolicy(),
    ) {
    }

    public static function make(): self
    {
        return new self(RubFamilyPremiumPolicy::fromStorageApp());
    }

    /**
     * Absolute AUTO_TARGET is disabled unless dual-gated (env + policy mode).
     */
    public function autoTargetEnabled(): bool
    {
        $env = (string) (getenv(self::AUTO_TARGET_ENV)
            ?: ($_ENV[self::AUTO_TARGET_ENV] ?? $_SERVER[self::AUTO_TARGET_ENV] ?? ''));
        if (!in_array(strtolower(trim($env)), ['1', 'true', 'yes', 'on'], true)) {
            return false;
        }

        $raw = $this->policy->raw();
        $mode = strtoupper((string) (
            $raw['intentional_commercial']['commercial_mode']
            ?? $raw['intentional_commercial']['mode']
            ?? 'MANUAL_PROFIT'
        ));

        return $mode === 'AUTO_TARGET';
    }

    /**
     * Absolute commercial target from policy (single source of truth).
     */
    public function absoluteTarget(?string $toCode = null): ?string
    {
        if ($toCode !== null) {
            $family = $this->policy->familyForDestination($toCode);
            if (is_array($family) && isset($family['target_commercial_usdt_rub'])
                && is_numeric($family['target_commercial_usdt_rub'])) {
                return $this->normalizeDecimal((string) $family['target_commercial_usdt_rub'], 8);
            }
        }

        $raw = $this->policy->raw();
        $intentional = $raw['intentional_commercial']['usdt_rub_customer_floating'] ?? null;

        if (!is_numeric($intentional)) {
            return null;
        }

        return $this->normalizeDecimal((string) $intentional, 8);
    }

    /**
     * profit = (1 - target/base) × 100
     */
    public static function profitForTarget(string $base, string $target, int $scale = self::PROFIT_SCALE): ?string
    {
        if (!is_numeric($base) || !is_numeric($target)) {
            return null;
        }
        if (bccomp($base, '0', 12) <= 0 || bccomp($target, '0', 12) <= 0) {
            return null;
        }

        return bcmul(bcsub('1', bcdiv($target, $base, 18), 18), '100', $scale);
    }

    /**
     * @param  list<string>|null  $fromCodes
     * @param  list<string>|null  $toCodes  when null, derive from policy intentional families
     * @return array{
     *   ok: bool,
     *   dry_run: bool,
     *   reason: ?string,
     *   policy_version: mixed,
     *   target_default: ?string,
     *   evaluated: int,
     *   written: int,
     *   skipped: int,
     *   failed: int,
     *   rows: list<array<string,mixed>>,
     *   locked: bool
     * }
     */
    public function sync(bool $dryRun = true, ?array $fromCodes = null, ?array $toCodes = null): array
    {
        $fromCodes = $fromCodes ?? self::DEFAULT_FROM_CODES;
        $fromCodes = array_values(array_filter(array_map(
            static fn (string $s) => strtoupper(trim($s)),
            $fromCodes,
        )));

        $result = [
            'ok' => false,
            'dry_run' => $dryRun,
            'reason' => null,
            'policy_version' => $this->policy->raw()['version'] ?? null,
            'target_default' => $this->absoluteTarget(),
            'evaluated' => 0,
            'written' => 0,
            'skipped' => 0,
            'failed' => 0,
            'rows' => [],
            'locked' => false,
        ];

        if (!$this->policy->isApproved()) {
            $result['reason'] = 'rub_policy_not_approved';

            return $result;
        }

        if (!$this->autoTargetEnabled()) {
            $result['ok'] = true;
            $result['reason'] = 'manual_profit_ownership';
            Log::info('usdt_rub_commercial_sync_skipped_manual_ownership', [
                'dry_run' => $dryRun,
                'policy_version' => $result['policy_version'],
            ]);

            return $result;
        }

        if ($result['target_default'] === null || bccomp((string) $result['target_default'], '50', 8) < 0
            || bccomp((string) $result['target_default'], '200', 8) > 0) {
            $result['reason'] = 'target_missing_or_out_of_bounds';

            return $result;
        }

        $toCodes = $toCodes ?? $this->eligibleDestinationCodes();
        if ($toCodes === []) {
            $result['reason'] = 'no_eligible_destinations';

            return $result;
        }

        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_TTL_SECONDS);
        if (!$lock->get()) {
            $result['reason'] = 'lock_busy';
            $result['locked'] = true;
            Log::warning('usdt_rub_commercial_sync_lock_busy', [
                'lock' => self::LOCK_KEY,
            ]);

            return $result;
        }

        try {
            $rows = $this->loadDirections($fromCodes, $toCodes);
            foreach ($rows as $row) {
                $result['evaluated']++;
                $item = $this->syncOne($row, $dryRun);
                $result['rows'][] = $item;
                if (($item['action'] ?? '') === 'written') {
                    $result['written']++;
                } elseif (($item['action'] ?? '') === 'failed') {
                    $result['failed']++;
                } else {
                    $result['skipped']++;
                }
            }

            $result['ok'] = $result['failed'] === 0 && $result['reason'] === null;
            if ($result['ok'] && !$dryRun) {
                Cache::put(self::LAST_SYNC_CACHE_KEY, [
                    'at' => gmdate('c'),
                    'written' => $result['written'],
                    'evaluated' => $result['evaluated'],
                    'policy_version' => $result['policy_version'],
                    'target' => $result['target_default'],
                ], now()->addDays(2));
            }

            Log::info('usdt_rub_commercial_sync_cycle', [
                'dry_run' => $dryRun,
                'ok' => $result['ok'],
                'policy_version' => $result['policy_version'],
                'target' => $result['target_default'],
                'evaluated' => $result['evaluated'],
                'written' => $result['written'],
                'skipped' => $result['skipped'],
                'failed' => $result['failed'],
            ]);

            return $result;
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * @return list<string>
     */
    public function eligibleDestinationCodes(): array
    {
        $raw = $this->policy->raw();
        $families = $raw['families'] ?? [];
        $codes = [];
        if (!is_array($families)) {
            return [];
        }
        foreach ($families as $key => $family) {
            if (!is_array($family)) {
                continue;
            }
            $hasAbsolute = isset($family['target_commercial_usdt_rub'])
                && is_numeric($family['target_commercial_usdt_rub']);
            $applies = $raw['intentional_commercial']['applies_to_families'] ?? [];
            $inApplies = is_array($applies) && in_array((string) $key, $applies, true);
            if (!$hasAbsolute && !$inApplies) {
                continue;
            }
            foreach ($family['destination_codes'] ?? [] as $code) {
                $codes[] = strtoupper((string) $code);
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * @param  list<string>  $fromCodes
     * @param  list<string>  $toCodes
     * @return list<object>
     */
    private function loadDirections(array $fromCodes, array $toCodes): array
    {
        $placeholdersFrom = implode(',', array_fill(0, count($fromCodes), '?'));
        $placeholdersTo = implode(',', array_fill(0, count($toCodes), '?'));

        return DB::select(
            "SELECT de.id, cb.designation_xml AS fr, cs.designation_xml AS tto,
                    de.status, de.course_value, de.profit, de.floating_fee, de.fix_fee,
                    de.parser_source_name, de.is_type_rate, de.deleted_at
             FROM direction_exchange de
             JOIN currencies cb ON cb.id = de.id_currency1
             JOIN currencies cs ON cs.id = de.id_currency2
             WHERE de.deleted_at IS NULL
               AND cb.designation_xml IN ({$placeholdersFrom})
               AND cs.designation_xml IN ({$placeholdersTo})
             ORDER BY cs.designation_xml, cb.designation_xml, de.id",
            array_merge($fromCodes, $toCodes),
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function syncOne(object $row, bool $dryRun): array
    {
        $id = (int) $row->id;
        $from = (string) $row->fr;
        $to = (string) $row->tto;
        $base = $this->normalizeDecimal((string) $row->course_value, 12);
        $profitBefore = $this->normalizeDecimal((string) ($row->profit ?? '0'), self::PROFIT_SCALE);
        $target = $this->absoluteTarget($to) ?? $this->absoluteTarget();

        $item = [
            'direction_id' => $id,
            'from' => $from,
            'to' => $to,
            'base' => $base,
            'target' => $target,
            'profit_before' => $profitBefore,
            'profit_after' => $profitBefore,
            'effective_before' => null,
            'effective_after' => null,
            'action' => 'skipped',
            'reason' => null,
            'write' => false,
        ];

        if ($target === null) {
            $item['action'] = 'failed';
            $item['reason'] = 'target_missing';

            return $item;
        }

        if (!$this->policy->isUsdtStableFrom($from)) {
            $item['reason'] = 'not_usdt_stable_from';

            return $item;
        }

        if (!$this->policy->familyUsesIntentionalAbsoluteTarget($to)) {
            $item['reason'] = 'destination_not_intentional_absolute';

            return $item;
        }

        if (bccomp($base, '0', 12) <= 0) {
            $item['action'] = 'failed';
            $item['reason'] = 'non_positive_base';

            return $item;
        }

        if (!$this->policy->isUsdtRubScaleBaseline((float) $base)) {
            $item['action'] = 'failed';
            $item['reason'] = 'base_out_of_usdt_rub_scale';

            return $item;
        }

        $parser = (string) ($row->parser_source_name ?? '');
        if ($parser !== '' && $parser !== 'DERIVED_MARKET_BASELINE') {
            $item['reason'] = 'parser_not_derived';

            return $item;
        }

        $profitNew = self::profitForTarget($base, $target);
        if ($profitNew === null) {
            $item['action'] = 'failed';
            $item['reason'] = 'profit_math_failed';

            return $item;
        }

        // Refuse frozen legacy literal even if math coincidentally matches (CBR drift makes this unlikely).
        if (bccomp($profitNew, self::LEGACY_MAGIC_PROFIT, 6) === 0) {
            // Recompute at higher scale then re-round — avoid storing the banned literal.
            $profitNew = self::profitForTarget($base, $target, 8);
            $profitNew = $this->normalizeDecimal((string) $profitNew, self::PROFIT_SCALE);
            if (bccomp($profitNew, self::LEGACY_MAGIC_PROFIT, 6) === 0) {
                $profitNew = bcadd($profitNew, '0.000001', self::PROFIT_SCALE);
            }
        }

        $absProfit = ltrim($profitNew, '+');
        if (str_starts_with($absProfit, '-')) {
            $absProfit = substr($absProfit, 1);
        }
        if (bccomp($absProfit, self::PROFIT_ABS_HARD_SANITY, 6) > 0) {
            $item['action'] = 'failed';
            $item['reason'] = 'profit_abs_exceeds_sanity';
            $item['profit_after'] = $profitNew;

            return $item;
        }

        $hardMax = $this->policy->hardMaximumPremiumPercent($to);
        if ($hardMax !== null && (float) $absProfit - $hardMax > 1e-9) {
            $item['action'] = 'failed';
            $item['reason'] = 'profit_abs_exceeds_family_hard_maximum';
            $item['profit_after'] = $profitNew;

            return $item;
        }

        $effectiveBefore = $this->effectiveFloating($base, $profitBefore);
        $effectiveAfter = $this->effectiveFloating($base, $profitNew);
        $item['effective_before'] = $effectiveBefore;
        $item['effective_after'] = $effectiveAfter;
        $item['profit_after'] = $profitNew;

        $gap = $this->absDiff($effectiveAfter, $target);
        if ($gap !== null && bccomp($gap, self::EFFECTIVE_GAP_TOLERANCE, 8) > 0) {
            $item['action'] = 'failed';
            $item['reason'] = 'effective_misses_target_tolerance';

            return $item;
        }

        $profitChanged = bccomp($profitBefore, $profitNew, self::PROFIT_SCALE) !== 0;
        $beforeGap = $this->absDiff($effectiveBefore, $target);
        $needsRewrite = $profitChanged
            || ($beforeGap !== null && bccomp($beforeGap, self::EFFECTIVE_GAP_TOLERANCE, 8) > 0);

        if (!$needsRewrite) {
            $item['reason'] = 'already_on_target';

            return $item;
        }

        if ($dryRun) {
            $item['action'] = 'planned';
            $item['reason'] = 'dry_run';
            $item['write'] = false;

            return $item;
        }

        try {
            CommercialAdjustmentWriteGate::run(self::WRITER, function () use ($id, $profitNew): void {
                $dir = DirectionExchange::query()->find($id);
                if (!$dir) {
                    throw new \RuntimeException('direction_missing');
                }
                $dir->profit = $profitNew;
                // Classic intentional track uses profit only; keep floating_fee neutral.
                if (bccomp((string) ($dir->floating_fee ?? '0'), '0', 8) !== 0) {
                    $dir->floating_fee = 0;
                }
                $dir->save();
            });
        } catch (Throwable $e) {
            $item['action'] = 'failed';
            $item['reason'] = 'write_failed:'.$e->getMessage();
            Log::error('usdt_rub_commercial_sync_write_failed', [
                'direction_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $item;
        }

        $item['action'] = 'written';
        $item['write'] = true;
        $item['reason'] = 'synced_to_policy_target';

        Log::info('usdt_rub_commercial_sync_direction', [
            'policy_version' => $this->policy->raw()['version'] ?? null,
            'direction_id' => $id,
            'from' => $from,
            'to' => $to,
            'baseline' => $base,
            'target' => $target,
            'profit_before' => $profitBefore,
            'profit_after' => $profitNew,
            'effective_before' => $effectiveBefore,
            'effective_after' => $effectiveAfter,
            'write' => true,
            'reason' => $item['reason'],
        ]);

        return $item;
    }

    private function effectiveFloating(string $base, string $profit): string
    {
        // final = base × (1 - profit/100)  === base × (1 + (-profit)/100)
        $fee = bcmul($profit, '-1', 12);

        return bcmul($base, bcadd('1', bcdiv($fee, '100', 18), 18), 12);
    }

    private function absDiff(?string $a, ?string $b): ?string
    {
        if ($a === null || $b === null || !is_numeric($a) || !is_numeric($b)) {
            return null;
        }
        $d = bcsub($a, $b, 12);

        return str_starts_with($d, '-') ? substr($d, 1) : $d;
    }

    private function normalizeDecimal(string $value, int $scale): string
    {
        $value = trim($value);
        if ($value === '' || !is_numeric($value)) {
            return bcmul('0', '1', $scale);
        }

        return bcmul($value, '1', $scale);
    }

    /**
     * Health helper: count rows still holding frozen legacy magic profit.
     */
    public static function legacyMagicProfitCount(): int
    {
        $to = ['SBERRUB', 'TBRUB', 'TCSBRUB', 'SBPRUB', 'RFBRUB', 'ACRUB'];
        $row = DB::selectOne(
            "SELECT COUNT(*) AS c
             FROM direction_exchange d
             JOIN currencies c1 ON c1.id = d.id_currency1
             JOIN currencies c2 ON c2.id = d.id_currency2
             WHERE d.deleted_at IS NULL
               AND d.status = 1
               AND c1.designation_xml LIKE 'USDT%'
               AND c2.designation_xml IN ('SBERRUB','TBRUB','TCSBRUB','SBPRUB','RFBRUB','ACRUB')
               AND ABS(d.profit + 15.661082) < 0.000001"
        );

        return (int) ($row->c ?? 0);
    }
}
