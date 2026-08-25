<?php

declare(strict_types=1);

namespace App\Services\Rates;

use App\Models\BestChangeDirection;
use App\Models\DirectionExchange;

/**
 * Operator-facing rate source labels for admin list/detail.
 *
 * Presentation only: does not change parser_source_name, ownership, or pricing.
 */
final class AdminRateSourcePresenter
{
    public static function label(DirectionExchange $direction): string
    {
        $src = trim((string) ($direction->parser_source_name ?? ''));
        $zelleId = (int) ZelleOutgoingRateWriteGuard::zelleCurrencyId();
        $isZelle = $src === ZelleUsdUsdtBenchmarkAuthority::PARSER_SOURCE_NAME
            || (int) ($direction->id_currency1 ?? 0) === $zelleId;

        if ($isZelle) {
            return 'Zelle · свой курс';
        }

        if ($src === '') {
            return 'Не задан';
        }

        if (preg_match('/ручн|manual/iu', $src) === 1) {
            return 'Ручной';
        }

        if ($src === 'DERIVED_MARKET_BASELINE') {
            return 'Внутренний рынок · AUTO';
        }

        if ($src === 'BestChange') {
            $pos = self::bestChangePosition($direction);
            if ($pos !== null) {
                return 'BestChange · позиция ' . $pos;
            }

            return 'BestChange';
        }

        return 'Автоматический (' . $src . ')';
    }

    private static function bestChangePosition(DirectionExchange $direction): ?string
    {
        if (!$direction->relationLoaded('bestchange_directions')) {
            return null;
        }

        $bc = $direction->getRelation('bestchange_directions');
        if (!$bc instanceof BestChangeDirection) {
            return null;
        }

        if ((int) ($bc->status ?? 0) !== 1) {
            return null;
        }

        $raw = trim((string) ($bc->position_num ?? ''));
        if ($raw === '' || $raw === '0') {
            return null;
        }

        return $raw;
    }
}
