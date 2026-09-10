<?php

declare(strict_types=1);

namespace App\Services\Rates;

use App\Models\DirectionExchange;
use App\Settings\BestChangeConfig;

/**
 * Model B — Coin→ZELLEUSD final-rate competitive controller.
 *
 * Scope: only when currency2 is ZELLEUSD and BestChange owns the direction.
 * Keeps position_num as SOURCE selector; adjusts canonical course_value so the
 * exported XML out beats live external top1 by the minimum winning export tick,
 * subject to min_safe_margin_vs_top1_bps.
 *
 * Does not write feed-only rates: site floating and XML both use course_value.
 */
final class CoinZelleExternalPositionController
{
    public const SCALE = 18;

    public function __construct(
        private readonly BestChangeConfig $config,
    ) {}

    /**
     * @param  array<int, array<string,mixed>>  $sortedBookAsc  BC rows sorted ASC by raw rate (own id may be present)
     * @return array{
     *   applied: bool,
     *   course: string,
     *   reason: string,
     *   top1_inv: ?string,
     *   target_course: ?string,
     *   exported_out: ?string,
     *   margin_vs_top1_bps: ?float,
     *   margin_guard_blocked_top1: bool,
     *   outlier_top1: bool,
     *   explain: array<string,mixed>
     * }
     */
    public function adjust(
        DirectionExchange $direction,
        string $baseCourse,
        array $sortedBookAsc,
        int $exportDecimals = 4,
    ): array {
        $baseCourse = $this->bc($baseCourse);
        $empty = [
            'applied' => false,
            'course' => $baseCourse,
            'reason' => 'skipped',
            'top1_inv' => null,
            'target_course' => null,
            'exported_out' => null,
            'margin_vs_top1_bps' => null,
            'margin_guard_blocked_top1' => false,
            'outlier_top1' => false,
            'explain' => [],
        ];

        if (! $this->config->coinZelleExternalPositionEnabled()) {
            return $empty + ['reason' => 'controller_disabled'];
        }

        if (! $this->isCoinToZelle($direction)) {
            return $empty + ['reason' => 'not_coin_to_zelle'];
        }

        $allow = $this->config->coinZelleControllerDirectionIds();
        if ($allow !== [] && ! in_array((int) $direction->id, $allow, true)) {
            return $empty + ['reason' => 'direction_not_in_allowlist'];
        }

        $ownId = $this->config->ownExchangerId();
        $competitors = [];
        foreach ($sortedBookAsc as $row) {
            $changer = (int) ($row['changer'] ?? 0);
            if ($ownId > 0 && $changer === $ownId) {
                continue;
            }
            $raw = $this->bc((string) ($row['rate'] ?? '0'));
            if (bccomp($raw, '0', self::SCALE) !== 1) {
                continue;
            }
            $competitors[] = [
                'changer' => $changer,
                'raw' => $raw,
                'inv' => bcdiv('1', $raw, self::SCALE),
            ];
        }

        $depth = count($competitors);
        $minDepth = $this->config->coinZelleMinBookDepth();
        if ($depth < $minDepth) {
            return $empty + [
                'reason' => 'thin_book',
                'explain' => ['book_depth' => $depth, 'min_depth' => $minDepth],
            ];
        }

        $top1 = $competitors[0];
        $top1Inv = $top1['inv'];
        $outlier = $this->isOutlierTop1($competitors);
        if ($outlier && $this->config->coinZelleOutlierGuardEnabled()) {
            // Keep BASE (or current) — do not chase anomalous #1.
            $export = $this->exportOut($baseCourse, $exportDecimals);
            return [
                'applied' => false,
                'course' => $baseCourse,
                'reason' => 'outlier_top1_guard',
                'top1_inv' => $top1Inv,
                'target_course' => null,
                'exported_out' => $export,
                'margin_vs_top1_bps' => $this->marginBps($top1Inv, $baseCourse),
                'margin_guard_blocked_top1' => false,
                'outlier_top1' => true,
                'explain' => [
                    'source_position' => '1',
                    'external_target' => $this->config->coinZelleExternalPositionTarget(),
                    'top1_inv' => $top1Inv,
                    'top2_inv' => $competitors[1]['inv'] ?? null,
                    'book_depth' => $depth,
                ],
            ];
        }

        $targetOut = $this->minimumWinningExportOut($top1Inv, $exportDecimals);
        $targetCourse = $this->minimumCourseForExportOut($targetOut, $exportDecimals);
        $marginBps = $this->marginBps($top1Inv, $targetCourse);
        $floor = $this->config->coinZelleMinSafeMarginVsTop1Bps();

        $explain = [
            'source_position' => '1',
            'external_target' => $this->config->coinZelleExternalPositionTarget(),
            'top1_inv' => $top1Inv,
            'top1_changer' => $top1['changer'],
            'base_course' => $baseCourse,
            'target_course' => $targetCourse,
            'target_export_out' => $targetOut,
            'margin_vs_top1_bps' => $marginBps,
            'min_safe_margin_vs_top1_bps' => $floor,
            'competitive_edge_mode' => 'minimum_winning_increment',
            'export_decimals' => $exportDecimals,
            'book_depth' => $depth,
        ];

        // Already wins after export of BASE?
        $baseExport = $this->exportOut($baseCourse, $exportDecimals);
        if (bccomp($baseExport, $top1Inv, self::SCALE) === 1) {
            return [
                'applied' => false,
                'course' => $baseCourse,
                'reason' => 'already_beats_top1_after_export',
                'top1_inv' => $top1Inv,
                'target_course' => $targetCourse,
                'exported_out' => $baseExport,
                'margin_vs_top1_bps' => $this->marginBps($top1Inv, $baseCourse),
                'margin_guard_blocked_top1' => false,
                'outlier_top1' => false,
                'explain' => $explain + ['status' => 'already_rank1_capable'],
            ];
        }

        if ($marginBps < $floor) {
            // Best safe: keep BASE (matching source economics); do not cross floor.
            return [
                'applied' => false,
                'course' => $baseCourse,
                'reason' => 'margin_guard_blocked_top1',
                'top1_inv' => $top1Inv,
                'target_course' => $targetCourse,
                'exported_out' => $baseExport,
                'margin_vs_top1_bps' => $marginBps,
                'margin_guard_blocked_top1' => true,
                'outlier_top1' => false,
                'explain' => $explain + ['status' => 'BEST_SAFE_RANK'],
            ];
        }

        // Anti-chase: never move more than one export tick beyond the minimum winner.
        $maxCourse = $targetCourse;
        if (bccomp($baseCourse, $maxCourse, self::SCALE) === 1) {
            // BASE already higher than needed — keep BASE (better for customer / rank).
            return [
                'applied' => false,
                'course' => $baseCourse,
                'reason' => 'base_already_ge_target',
                'top1_inv' => $top1Inv,
                'target_course' => $targetCourse,
                'exported_out' => $baseExport,
                'margin_vs_top1_bps' => $this->marginBps($top1Inv, $baseCourse),
                'margin_guard_blocked_top1' => false,
                'outlier_top1' => false,
                'explain' => $explain,
            ];
        }

        return [
            'applied' => true,
            'course' => $targetCourse,
            'reason' => 'applied_minimum_winning_tick',
            'top1_inv' => $top1Inv,
            'target_course' => $targetCourse,
            'exported_out' => $targetOut,
            'margin_vs_top1_bps' => $marginBps,
            'margin_guard_blocked_top1' => false,
            'outlier_top1' => false,
            'explain' => $explain + ['status' => 'TARGET_RANK1'],
        ];
    }

    public function isCoinToZelle(DirectionExchange $direction): bool
    {
        $direction->loadMissing(['currency1', 'currency2']);
        $to = (string) ($direction->currency2->designation_xml ?? '');
        $from = (string) ($direction->currency1->designation_xml ?? '');
        if ($to !== 'ZELLEUSD' || $from === '' || $from === 'ZELLEUSD') {
            return false;
        }
        // Crypto group / non-fiat: designation typically matches deposit coins.
        return (string) ($direction->parser_source_name ?? '') === 'BestChange'
            || $direction->bestchange_directions()->where('status', 1)->exists();
    }

    /**
     * Match ExportFormatHelpers::roundBcmath half-up semantics.
     */
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
            // rate < 1 → XML uses in=1/rate, out=1; competitive metric remains course (inv).
            return $course;
        }

        return $this->roundBcmath($course, $decimals);
    }

    /**
     * Smallest export-out (at $decimals) that is strictly greater than $top1Inv.
     */
    public function minimumWinningExportOut(string $top1Inv, int $decimals): string
    {
        $top1Inv = $this->bc($top1Inv);
        $tick = bcpow('10', (string) (-$decimals), $decimals);
        // Start at ceil(top1) to decimals, then ensure strictly greater.
        $rounded = $this->roundBcmath($top1Inv, $decimals);
        if (bccomp($rounded, $top1Inv, self::SCALE) === 1) {
            return $rounded;
        }

        return bcadd($rounded, $tick, $decimals);
    }

    /**
     * Minimum course that roundBcmath(..., $decimals) equals $desiredOut.
     * Half-up threshold: desiredOut - 0.5 tick.
     */
    public function minimumCourseForExportOut(string $desiredOut, int $decimals): string
    {
        $desiredOut = $this->bc($desiredOut);
        $half = bcpow('10', (string) (-($decimals + 1)), $decimals + 1);

        return bcsub($desiredOut, $half, self::SCALE);
    }

    /**
     * Positive = we give customer less than top1 (safer). Negative = we beat top1 (cost).
     */
    public function marginBps(string $top1Inv, string $ourCourse): float
    {
        $top1Inv = $this->bc($top1Inv);
        $ourCourse = $this->bc($ourCourse);
        if (bccomp($top1Inv, '0', self::SCALE) !== 1) {
            return 0.0;
        }
        $ratio = bcsub(bcdiv($top1Inv, $ourCourse, self::SCALE), '1', self::SCALE);

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
        $threshold = $this->config->coinZelleOutlierSepBps();

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
