<?php

declare(strict_types=1);

namespace App\Services\Rates;

use App\Models\BestChangeDirection;
use App\Models\DirectionExchange;
use App\Settings\BestChangeConfig;

/**
 * Generic BestChange external Top-N competitive controller (Model B).
 *
 * Semantics (AT_OR_BETTER_THAN_N):
 * - source_position (position_num) still selects competitor BASE upstream
 * - external_target_position (= position_num) is desired public Top-N
 * - if actual_rank <= N → keep (never worsen)
 * - if actual_rank > N → apply minimum export-tick to beat boundary at N, subject to margin/outlier/sanity
 *
 * Eligibility is gated by certified pair families (config). Uncertified families are no-ops.
 */
final class BestChangeExternalTopNController
{
    public const SCALE = 18;

    public const FAMILY_COIN_TO_ZELLE = 'COIN_TO_ZELLE';

    public const FAMILY_ZELLE_TO_COIN = 'ZELLE_TO_COIN';

    public const FAMILY_COIN_TO_COIN = 'COIN_TO_COIN';

    public const FAMILY_COIN_TO_FIAT_BANK = 'COIN_TO_FIAT_BANK';

    public const FAMILY_FIAT_BANK_TO_COIN = 'FIAT_BANK_TO_COIN';

    public const FAMILY_FIAT_TO_FIAT = 'FIAT_TO_FIAT';

    public const STATUS_TARGET_PASS = 'TARGET_PASS';

    public const STATUS_BETTER_THAN_TARGET = 'BETTER_THAN_TARGET';

    public const STATUS_BEST_SAFE_RANK = 'BEST_SAFE_RANK';

    public const STATUS_BLOCKED_MARGIN = 'BLOCKED_MARGIN';

    public const STATUS_BLOCKED_OUTLIER = 'BLOCKED_OUTLIER';

    public const STATUS_BOOK_TOO_THIN = 'BOOK_TOO_THIN';

    public const STATUS_NOT_ELIGIBLE = 'NOT_ELIGIBLE';

    public function __construct(
        private readonly BestChangeConfig $config,
    ) {}

    /**
     * @param  array<int, array<string,mixed>>  $sortedBookAsc  BC rows sorted ASC by raw rate
     * @return array{
     *   applied: bool,
     *   course: string,
     *   reason: string,
     *   status: string,
     *   family: ?string,
     *   source_position: ?int,
     *   external_target_position: ?int,
     *   effective_target: ?int,
     *   actual_rank: ?int,
     *   boundary_inv: ?string,
     *   target_course: ?string,
     *   exported_out: ?string,
     *   margin_vs_boundary_bps: ?float,
     *   margin_guard_blocked: bool,
     *   outlier_top1: bool,
     *   explain: array<string,mixed>
     * }
     */
    public function adjust(
        DirectionExchange $direction,
        string $baseCourse,
        array $sortedBookAsc,
        int $exportDecimals = 4,
        ?int $sourcePosition = null,
        ?int $externalTargetPosition = null,
    ): array {
        $baseCourse = $this->bc($baseCourse);
        $empty = [
            'applied' => false,
            'course' => $baseCourse,
            'reason' => 'skipped',
            'status' => self::STATUS_NOT_ELIGIBLE,
            'family' => null,
            'source_position' => $sourcePosition,
            'external_target_position' => $externalTargetPosition,
            'effective_target' => null,
            'actual_rank' => null,
            'boundary_inv' => null,
            'target_course' => null,
            'exported_out' => null,
            'margin_vs_boundary_bps' => null,
            'margin_guard_blocked' => false,
            'outlier_top1' => false,
            'explain' => [],
        ];

        if (! $this->config->externalTopNEnabled()) {
            // Legacy Coin→Zelle master switch still honored via family bridge.
            if (! $this->config->coinZelleExternalPositionEnabled()) {
                return array_merge($empty, ['reason' => 'controller_disabled']);
            }
        }

        $family = $this->classifyFamily($direction);
        if ($family === null || ! $this->config->externalTopNFamilyEnabled($family)) {
            // Bridge: Coin→Zelle legacy enable path.
            if ($family === self::FAMILY_COIN_TO_ZELLE && $this->config->coinZelleExternalPositionEnabled()) {
                // continue
            } else {
                return array_merge($empty, [
                    'reason' => 'family_not_eligible',
                    'family' => $family,
                    'status' => self::STATUS_NOT_ELIGIBLE,
                ]);
            }
        }

        // Optional direction allowlist (legacy coin_zelle allowlist OR global).
        $allow = $this->config->externalTopNDirectionIds();
        if ($allow === [] && $family === self::FAMILY_COIN_TO_ZELLE) {
            $allow = $this->config->coinZelleControllerDirectionIds();
        }
        if ($allow !== [] && ! in_array((int) $direction->id, $allow, true)) {
            return array_merge($empty, [
                'reason' => 'direction_not_in_allowlist',
                'family' => $family,
                'status' => self::STATUS_NOT_ELIGIBLE,
            ]);
        }

        $ownId = $this->config->ownExchangerId();
        $fullAsc = [];
        foreach ($sortedBookAsc as $row) {
            $raw = $this->bc((string) ($row['rate'] ?? '0'));
            if (bccomp($raw, '0', self::SCALE) !== 1) {
                continue;
            }
            $fullAsc[] = [
                'changer' => (int) ($row['changer'] ?? 0),
                'raw' => $raw,
                'inv' => bcdiv('1', $raw, self::SCALE),
            ];
        }

        $competitors = array_values(array_filter(
            $fullAsc,
            static fn (array $r): bool => $ownId <= 0 || $r['changer'] !== $ownId
        ));

        $depth = count($competitors);
        $minDepth = $this->minBookDepthForFamily($family);
        if ($depth < $minDepth) {
            return array_merge($empty, [
                'reason' => 'thin_book',
                'family' => $family,
                'status' => self::STATUS_BOOK_TOO_THIN,
                'explain' => ['book_depth' => $depth, 'min_depth' => $minDepth],
            ]);
        }

        $actualRank = null;
        foreach ($fullAsc as $i => $row) {
            if ($ownId > 0 && $row['changer'] === $ownId) {
                $actualRank = $i + 1;
                break;
            }
        }

        $configuredTarget = max(1, $externalTargetPosition
            ?? $sourcePosition
            ?? $this->config->coinZelleExternalPositionTarget());
        // Thin-book: clamp target to available competitor depth.
        $effectiveTarget = min($configuredTarget, max(1, $depth));

        // Already at or better than Top-N → never worsen (before outlier/chase logic).
        if ($actualRank !== null && $actualRank <= $configuredTarget) {
            $status = $actualRank < $configuredTarget
                ? self::STATUS_BETTER_THAN_TARGET
                : self::STATUS_TARGET_PASS;
            $boundaryInvEarly = $competitors[0]['inv'] ?? null;

            return [
                'applied' => false,
                'course' => $baseCourse,
                'reason' => 'already_at_or_better_than_target',
                'status' => $status,
                'family' => $family,
                'source_position' => $sourcePosition ?? $configuredTarget,
                'external_target_position' => $configuredTarget,
                'effective_target' => $effectiveTarget,
                'actual_rank' => $actualRank,
                'boundary_inv' => $boundaryInvEarly,
                'target_course' => null,
                'exported_out' => $this->exportOut($baseCourse, $exportDecimals),
                'margin_vs_boundary_bps' => $boundaryInvEarly !== null
                    ? $this->marginBps($boundaryInvEarly, $baseCourse)
                    : null,
                'margin_guard_blocked' => false,
                'outlier_top1' => false,
                'explain' => [
                    'source_position' => $sourcePosition ?? $configuredTarget,
                    'external_target' => $configuredTarget,
                    'effective_target' => $effectiveTarget,
                    'actual_rank' => $actualRank,
                    'book_depth' => $depth,
                    'family' => $family,
                    'target_mode' => 'AT_OR_BETTER_THAN_N',
                    'status' => $status,
                ],
            ];
        }

        $boundary = $competitors[$effectiveTarget - 1];
        $boundaryInv = $boundary['inv'];

        $outlierEnabled = $this->config->externalTopNOutlierGuardEnabled();
        if ($family === self::FAMILY_COIN_TO_ZELLE) {
            $outlierEnabled = $outlierEnabled && $this->config->coinZelleOutlierGuardEnabled();
        }
        $outlier = $this->isOutlierTop1($competitors);
        if ($outlier && $outlierEnabled && $effectiveTarget === 1) {
            // Only block chase when trying to reach #1 against anomalous top1.
            return [
                'applied' => false,
                'course' => $baseCourse,
                'reason' => 'outlier_top1_guard',
                'status' => self::STATUS_BLOCKED_OUTLIER,
                'family' => $family,
                'source_position' => $sourcePosition ?? $configuredTarget,
                'external_target_position' => $configuredTarget,
                'effective_target' => $effectiveTarget,
                'actual_rank' => $actualRank,
                'boundary_inv' => $boundaryInv,
                'target_course' => null,
                'exported_out' => $this->exportOut($baseCourse, $exportDecimals),
                'margin_vs_boundary_bps' => $this->marginBps($boundaryInv, $baseCourse),
                'margin_guard_blocked' => false,
                'outlier_top1' => true,
                'explain' => [
                    'source_position' => $sourcePosition ?? $configuredTarget,
                    'external_target' => $configuredTarget,
                    'effective_target' => $effectiveTarget,
                    'top1_inv' => $competitors[0]['inv'],
                    'top2_inv' => $competitors[1]['inv'] ?? null,
                    'book_depth' => $depth,
                    'target_mode' => 'AT_OR_BETTER_THAN_N',
                ],
            ];
        }

        $explain = [
            'source_position' => $sourcePosition ?? $configuredTarget,
            'external_target' => $configuredTarget,
            'effective_target' => $effectiveTarget,
            'actual_rank' => $actualRank,
            'boundary_inv' => $boundaryInv,
            'boundary_changer' => $boundary['changer'],
            'base_course' => $baseCourse,
            'export_decimals' => $exportDecimals,
            'book_depth' => $depth,
            'family' => $family,
            'target_mode' => 'AT_OR_BETTER_THAN_N',
            'competitive_edge_mode' => 'minimum_winning_increment',
        ];

        // Also: if BASE/current export already beats boundary, treat as capable without raising further
        // (covers feed lag where our live rank is stale but course already wins).
        $baseExport = $this->exportOut($baseCourse, $exportDecimals);
        if (bccomp($baseExport, $boundaryInv, self::SCALE) >= 0) {
            // For strict beat when tied at export precision, still apply one tick if rank missing/worse.
            if (bccomp($baseExport, $boundaryInv, self::SCALE) === 1) {
                return [
                    'applied' => false,
                    'course' => $baseCourse,
                    'reason' => 'base_already_beats_boundary_after_export',
                    'status' => self::STATUS_TARGET_PASS,
                    'family' => $family,
                    'source_position' => $sourcePosition ?? $configuredTarget,
                    'external_target_position' => $configuredTarget,
                    'effective_target' => $effectiveTarget,
                    'actual_rank' => $actualRank,
                    'boundary_inv' => $boundaryInv,
                    'target_course' => null,
                    'exported_out' => $baseExport,
                    'margin_vs_boundary_bps' => $this->marginBps($boundaryInv, $baseCourse),
                    'margin_guard_blocked' => false,
                    'outlier_top1' => $outlier,
                    'explain' => $explain + ['status' => self::STATUS_TARGET_PASS],
                ];
            }
        }

        $targetOut = $this->minimumWinningExportOut($boundaryInv, $exportDecimals);
        $targetCourse = $this->minimumCourseForExportOut($targetOut, $exportDecimals);
        $marginBps = $this->marginBps($boundaryInv, $targetCourse);
        $floor = $this->config->externalTopNMinSafeMarginVsBoundaryBps();
        if ($family === self::FAMILY_COIN_TO_ZELLE) {
            // Preserve certified Coin→Zelle floor.
            $floor = $this->config->coinZelleMinSafeMarginVsTop1Bps();
        }
        $explain['target_course'] = $targetCourse;
        $explain['target_export_out'] = $targetOut;
        $explain['margin_vs_boundary_bps'] = $marginBps;
        $explain['min_safe_margin_vs_boundary_bps'] = $floor;

        if ($marginBps < $floor) {
            return [
                'applied' => false,
                'course' => $baseCourse,
                'reason' => 'margin_guard_blocked',
                'status' => self::STATUS_BLOCKED_MARGIN,
                'family' => $family,
                'source_position' => $sourcePosition ?? $configuredTarget,
                'external_target_position' => $configuredTarget,
                'effective_target' => $effectiveTarget,
                'actual_rank' => $actualRank,
                'boundary_inv' => $boundaryInv,
                'target_course' => $targetCourse,
                'exported_out' => $baseExport,
                'margin_vs_boundary_bps' => $marginBps,
                'margin_guard_blocked' => true,
                'outlier_top1' => $outlier,
                'explain' => $explain + ['status' => self::STATUS_BEST_SAFE_RANK],
            ];
        }

        if (bccomp($baseCourse, '0', self::SCALE) === 1) {
            $ratio = bcdiv($targetCourse, $baseCourse, self::SCALE);
            $jumpPct = abs((float) bcmul(bcsub($ratio, '1', self::SCALE), '100', 8));
            if ($jumpPct > 2.0) {
                return [
                    'applied' => false,
                    'course' => $baseCourse,
                    'reason' => 'sanity_jump_blocked',
                    'status' => self::STATUS_BEST_SAFE_RANK,
                    'family' => $family,
                    'source_position' => $sourcePosition ?? $configuredTarget,
                    'external_target_position' => $configuredTarget,
                    'effective_target' => $effectiveTarget,
                    'actual_rank' => $actualRank,
                    'boundary_inv' => $boundaryInv,
                    'target_course' => $targetCourse,
                    'exported_out' => $baseExport,
                    'margin_vs_boundary_bps' => $marginBps,
                    'margin_guard_blocked' => false,
                    'outlier_top1' => $outlier,
                    'explain' => $explain + ['status' => 'SANITY_BLOCK', 'jump_pct' => $jumpPct],
                ];
            }
        }

        // Never decrease rate when BASE already higher than needed (anti-worsen).
        if (bccomp($baseCourse, $targetCourse, self::SCALE) === 1) {
            return [
                'applied' => false,
                'course' => $baseCourse,
                'reason' => 'base_already_ge_target',
                'status' => self::STATUS_BETTER_THAN_TARGET,
                'family' => $family,
                'source_position' => $sourcePosition ?? $configuredTarget,
                'external_target_position' => $configuredTarget,
                'effective_target' => $effectiveTarget,
                'actual_rank' => $actualRank,
                'boundary_inv' => $boundaryInv,
                'target_course' => $targetCourse,
                'exported_out' => $baseExport,
                'margin_vs_boundary_bps' => $this->marginBps($boundaryInv, $baseCourse),
                'margin_guard_blocked' => false,
                'outlier_top1' => $outlier,
                'explain' => $explain,
            ];
        }

        return [
            'applied' => true,
            'course' => $targetCourse,
            'reason' => 'applied_minimum_winning_tick',
            'status' => self::STATUS_TARGET_PASS,
            'family' => $family,
            'source_position' => $sourcePosition ?? $configuredTarget,
            'external_target_position' => $configuredTarget,
            'effective_target' => $effectiveTarget,
            'actual_rank' => $actualRank,
            'boundary_inv' => $boundaryInv,
            'target_course' => $targetCourse,
            'exported_out' => $targetOut,
            'margin_vs_boundary_bps' => $marginBps,
            'margin_guard_blocked' => false,
            'outlier_top1' => $outlier,
            'explain' => $explain + ['status' => 'TARGET_TOP_N'],
        ];
    }

    public function classifyFamily(DirectionExchange $direction): ?string
    {
        $direction->loadMissing(['currency1.payment', 'currency2.payment']);
        // If currencies were eager-loaded with a column subset that omitted designation_xml,
        // reload full currency rows so family classification cannot silently collapse to null.
        foreach (['currency1', 'currency2'] as $rel) {
            $cur = $direction->{$rel};
            if ($cur && trim((string) ($cur->designation_xml ?? '')) === '' && (int) ($cur->id ?? 0) > 0) {
                $direction->unsetRelation($rel);
            }
        }
        $direction->loadMissing(['currency1', 'currency2', 'currency1.payment', 'currency2.payment']);
        $to = (string) ($direction->currency2->designation_xml ?? '');
        $from = (string) ($direction->currency1->designation_xml ?? '');
        if ($to === 'ZELLEUSD' && $from !== '' && $from !== 'ZELLEUSD') {
            return self::FAMILY_COIN_TO_ZELLE;
        }
        if ($from === 'ZELLEUSD' && $to !== '' && $to !== 'ZELLEUSD') {
            return self::FAMILY_ZELLE_TO_COIN;
        }
        if ($from === '' || $to === '') {
            return null;
        }

        $c1 = $this->isCryptoDesignation($from);
        $c2 = $this->isCryptoDesignation($to);
        if ($c1 && $c2) {
            return self::FAMILY_COIN_TO_COIN;
        }
        if ($c1 && ! $c2) {
            return self::FAMILY_COIN_TO_FIAT_BANK;
        }
        if (! $c1 && $c2) {
            return self::FAMILY_FIAT_BANK_TO_COIN;
        }
        // Cash/fiat/payment ↔ cash/fiat/payment (neither side crypto).
        return self::FAMILY_FIAT_TO_FIAT;
    }

    public function isEligible(DirectionExchange $direction): bool
    {
        $family = $this->classifyFamily($direction);
        if ($family === null) {
            return false;
        }
        if ($this->config->externalTopNFamilyEnabled($family)) {
            return true;
        }

        return $family === self::FAMILY_COIN_TO_ZELLE
            && $this->config->coinZelleExternalPositionEnabled();
    }

    private function minBookDepthForFamily(string $family): int
    {
        $configured = $this->config->externalTopNMinBookDepth();
        if ($family === self::FAMILY_COIN_TO_ZELLE) {
            return max($configured, $this->config->coinZelleMinBookDepth());
        }
        // Zelle→Coin public books are often only us + 1 competitor.
        if ($family === self::FAMILY_ZELLE_TO_COIN) {
            return 1;
        }

        return max(3, $configured);
    }

    private function isCryptoDesignation(string $xml): bool
    {
        return (bool) preg_match(
            '/^(USDT|USDC|BTC|ETH|LTC|XMR|XRP|SOL|TRX|BNB|DOGE|DOT|ADA|BCH|DASH|ETC|NEO|XLM|XTZ|ZEC|GRAM|SHIB|TON|MATIC|AVAX|LINK|ATOM)/i',
            $xml
        );
    }

    public function roundBcmath(string $number, int $decimals): string
    {
        $number = $this->bc($number);
        $factor = bcpow('10', (string) ($decimals + 1), 0);
        $temp = bcmul($number, $factor, 0);
        $lastDigit = (int) substr($temp, -1);
        $roundUp = $lastDigit >= 5 ? 1 : 0;
        $pow = bcpow('10', (string) $decimals, 0);

        return bcdiv(
            bcadd(bcmul($number, $pow, 0), (string) $roundUp, 0),
            $pow,
            $decimals
        );
    }

    public function exportOut(string $course, int $decimals): string
    {
        $course = $this->bc($course);
        if (bccomp($course, '1', self::SCALE) === -1) {
            return $course;
        }

        return $this->roundBcmath($course, $decimals);
    }

    public function minimumWinningExportOut(string $boundaryInv, int $decimals): string
    {
        $boundaryInv = $this->bc($boundaryInv);
        $tick = bcpow('10', (string) (-$decimals), $decimals);
        $rounded = $this->roundBcmath($boundaryInv, $decimals);
        if (bccomp($rounded, $boundaryInv, self::SCALE) === 1) {
            return $rounded;
        }

        return bcadd($rounded, $tick, $decimals);
    }

    public function minimumCourseForExportOut(string $desiredOut, int $decimals): string
    {
        $desiredOut = $this->bc($desiredOut);
        $half = bcpow('10', (string) (-($decimals + 1)), $decimals + 1);

        return bcsub($desiredOut, $half, self::SCALE);
    }

    /**
     * Positive = we give customer less than boundary (safer). Negative = we beat boundary (cost).
     */
    public function marginBps(string $boundaryInv, string $ourCourse): float
    {
        $boundaryInv = $this->bc($boundaryInv);
        $ourCourse = $this->bc($ourCourse);
        if (bccomp($boundaryInv, '0', self::SCALE) !== 1) {
            return 0.0;
        }
        $ratio = bcsub(bcdiv($boundaryInv, $ourCourse, self::SCALE), '1', self::SCALE);

        return (float) bcmul($ratio, '10000', 6);
    }

    /**
     * @param  array<int, array{inv: string}>  $competitors
     */
    private function isOutlierTop1(array $competitors): bool
    {
        if (count($competitors) < 3) {
            return true;
        }
        $a = $competitors[0]['inv'];
        $b = $competitors[1]['inv'];
        if (bccomp($b, '0', self::SCALE) !== 1) {
            return true;
        }
        $sepBps = (float) bcmul(bcsub(bcdiv($a, $b, self::SCALE), '1', self::SCALE), '10000', 6);
        $threshold = $this->config->externalTopNOutlierSepBps();
        if ($this->config->coinZelleOutlierSepBps() > 0) {
            // Prefer family-specific when present for Coin→Zelle parity.
            $threshold = $this->config->coinZelleOutlierSepBps();
        }

        return $sepBps > $threshold;
    }

    private function bc(string $v): string
    {
        $v = trim($v);
        if ($v === '' || ! is_numeric($v)) {
            return '0';
        }

        return bcadd($v, '0', self::SCALE);
    }
}
