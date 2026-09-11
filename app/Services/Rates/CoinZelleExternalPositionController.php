<?php

declare(strict_types=1);

namespace App\Services\Rates;

use App\Models\DirectionExchange;
use App\Settings\BestChangeConfig;

/**
 * Backward-compatible Coin→Zelle facade over BestChangeExternalTopNController.
 *
 * Preserves prior return keys used by recalculate/tests while delegating
 * AT_OR_BETTER_THAN_N Top-N math to the generic controller.
 */
final class CoinZelleExternalPositionController
{
    public const SCALE = BestChangeExternalTopNController::SCALE;

    public function __construct(
        private readonly BestChangeConfig $config,
        private readonly BestChangeExternalTopNController $topN,
    ) {}

    /**
     * @param  array<int, array<string,mixed>>  $sortedBookAsc
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
        ?int $sourcePosition = null,
        ?int $externalTargetPosition = null,
    ): array {
        // Legacy Coin→Zelle defaulted external target to config (usually 1).
        // When caller passes position_num, that becomes dynamic Top-N.
        $target = $externalTargetPosition
            ?? $sourcePosition
            ?? $this->config->coinZelleExternalPositionTarget();

        $adj = $this->topN->adjust(
            $direction,
            $baseCourse,
            $sortedBookAsc,
            $exportDecimals,
            $sourcePosition,
            $target,
        );

        $reason = (string) ($adj['reason'] ?? 'skipped');
        if ($reason === 'margin_guard_blocked') {
            $reason = 'margin_guard_blocked_top1';
        }
        if ($reason === 'family_not_eligible' && $this->isCoinToZelle($direction)) {
            // Should not happen when Coin→Zelle enabled; keep explicit.
            $reason = 'not_coin_to_zelle';
        }

        return [
            'applied' => (bool) ($adj['applied'] ?? false),
            'course' => (string) ($adj['course'] ?? $baseCourse),
            'reason' => $reason,
            'top1_inv' => $adj['boundary_inv'] ?? null,
            'target_course' => $adj['target_course'] ?? null,
            'exported_out' => $adj['exported_out'] ?? null,
            'margin_vs_top1_bps' => $adj['margin_vs_boundary_bps'] ?? null,
            'margin_guard_blocked_top1' => (bool) ($adj['margin_guard_blocked'] ?? false),
            'outlier_top1' => (bool) ($adj['outlier_top1'] ?? false),
            'explain' => (array) ($adj['explain'] ?? []),
            // Extended keys (non-breaking).
            'status' => $adj['status'] ?? null,
            'external_target_position' => $adj['external_target_position'] ?? null,
            'actual_rank' => $adj['actual_rank'] ?? null,
        ];
    }

    public function isCoinToZelle(DirectionExchange $direction): bool
    {
        return $this->topN->classifyFamily($direction) === BestChangeExternalTopNController::FAMILY_COIN_TO_ZELLE
            && (
                (string) ($direction->parser_source_name ?? '') === 'BestChange'
                || $direction->bestchange_directions()->where('status', 1)->exists()
            );
    }

    public function roundBcmath(string $number, int $decimals): string
    {
        return $this->topN->roundBcmath($number, $decimals);
    }

    public function exportOut(string $course, int $decimals): string
    {
        return $this->topN->exportOut($course, $decimals);
    }

    public function minimumWinningExportOut(string $top1Inv, int $decimals): string
    {
        return $this->topN->minimumWinningExportOut($top1Inv, $decimals);
    }

    public function minimumCourseForExportOut(string $desiredOut, int $decimals): string
    {
        return $this->topN->minimumCourseForExportOut($desiredOut, $decimals);
    }

    public function marginBps(string $top1Inv, string $ourCourse): float
    {
        return $this->topN->marginBps($top1Inv, $ourCourse);
    }
}
