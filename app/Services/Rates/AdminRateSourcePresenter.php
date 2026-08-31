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

        // Runtime BestChange ownership must display as BestChange even on ZELLEUSD→*.
        if ($src === 'BestChange') {
            $pos = self::bestChangePosition($direction);
            if ($pos !== null) {
                return 'BestChange · позиция ' . $pos;
            }

            return 'BestChange';
        }

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
            return 'Ручной курс';
        }

        if ($src === 'DERIVED_MARKET_BASELINE') {
            // Operator must never see the raw internal constant. When BestChange
            // cannot currently provide a usable position, say so explicitly —
            // a saved inactive position_num is configuration only.
            if (self::hasInactiveOrUnusableBestChangeLink($direction)) {
                return 'Внутренний рынок · AUTO · BestChange: нет доступной позиции';
            }

            return 'Внутренний рынок · AUTO';
        }

        return 'Автоматический (' . $src . ')';
    }

    /**
     * Operator-facing parser attribute for admin JSON/UI.
     *
     * Always returns {@see label()} so Vue fallbacks
     * (`rate_source_label || parser_source_name`) and toast payloads never
     * leak internal tokens such as DERIVED_MARKET_BASELINE.
     * DB ownership still uses DirectionExchange::$parser_source_name unchanged.
     */
    public static function displayParserName(DirectionExchange $direction): string
    {
        return self::label($direction);
    }

    /**
     * True when a string is a forbidden raw internal rate-source token.
     */
    public static function isRawInternalToken(?string $value): bool
    {
        $v = trim((string) $value);

        return $v === 'DERIVED_MARKET_BASELINE'
            || $v === ZelleUsdUsdtBenchmarkAuthority::PARSER_SOURCE_NAME;
    }

    /**
     * True when a BestChange row exists but is not the runtime owner
     * (inactive / error / non-positive) — i.e. Derived is a genuine fallback.
     */
    private static function hasInactiveOrUnusableBestChangeLink(DirectionExchange $direction): bool
    {
        if (!$direction->relationLoaded('bestchange_directions')) {
            return false;
        }

        $bc = $direction->getRelation('bestchange_directions');
        if (!$bc instanceof BestChangeDirection) {
            return false;
        }

        if ((int) ($bc->status ?? 0) === 1
            && (int) ($bc->is_error_parser ?? 0) === 0
            && is_numeric((string) ($bc->rate_value ?? ''))
            && (float) $bc->rate_value > 0
        ) {
            return false;
        }

        // Row present (incl. saved position) but not usable for current rate.
        return true;
    }

    /**
     * True only when runtime owner is BestChange and the BC link is active.
     * Saved position_num on inactive/derived rows is configuration only — not authoritative.
     */
    public static function isBestChangePositionAuthoritative(DirectionExchange $direction): bool
    {
        if (trim((string) ($direction->parser_source_name ?? '')) !== 'BestChange') {
            return false;
        }

        if (!$direction->relationLoaded('bestchange_directions')) {
            return false;
        }

        $bc = $direction->getRelation('bestchange_directions');
        if (!$bc instanceof BestChangeDirection) {
            return false;
        }

        return (int) ($bc->status ?? 0) === 1;
    }

    /**
     * Operator-facing live customer rate (Canonical floating final), not market BASE.
     *
     * Calculator/exchange_rate DB often store pre-profit BASE for derived-config rails
     * even when BestChange is the runtime owner — that must not be shown as the live rate.
     */
    public static function liveExchangeRateLabel(DirectionExchange $direction): string
    {
        $base = trim((string) ($direction->course_value ?? ''));
        if ($base === '' || !is_numeric($base) || (float) $base <= 0) {
            return (string) ($direction->exchange_rate ?? 'Курс недоступен');
        }

        try {
            $commercial = CanonicalDirectionRateCalculator::make()
                ->calculateForExport($direction, $base);
            $rate = ($commercial->eligible && bccomp($commercial->finalRate, '0', 18) === 1)
                ? $commercial->finalRate
                : $base;
        } catch (\Throwable) {
            $rate = $base;
        }

        $from = self::currencySign($direction, true);
        $to = self::currencySign($direction, false);
        if ($from === null || $to === null) {
            return (string) ($direction->exchange_rate ?? $rate);
        }

        // Live operator rate: always format with full bc scale then trim zeros.
        // currency.number_format alone collapses SHIB→fiat (nf=4) and SHIB→BTC (~1e-11).
        $formatted = self::formatRateString($rate, 18);

        return sprintf('1 %s = %s %s', $from, $formatted, $to);
    }

    private static function formatRateString(string $rate, int $decimals): string
    {
        $decimals = max(0, min(18, $decimals));
        $normalized = bcadd($rate, '0', $decimals);
        $formatted = rtrim(rtrim($normalized, '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }

    private static function currencySign(DirectionExchange $direction, bool $from): ?string
    {
        try {
            $currency = $from ? $direction->currency1 : $direction->currency2;
            $name = $currency?->code_currency?->name ?? null;
            if (is_string($name) && $name !== '') {
                return $name;
            }
            $tech = $currency?->tech_name ?? null;

            return is_string($tech) && $tech !== '' ? $tech : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function bestChangePosition(DirectionExchange $direction): ?string
    {
        if (!self::isBestChangePositionAuthoritative($direction)) {
            return null;
        }

        $bc = $direction->getRelation('bestchange_directions');
        $raw = trim((string) ($bc->position_num ?? ''));
        if ($raw === '' || $raw === '0') {
            // Inherit global default for operator-facing label when per-pair position unset.
            try {
                $global = max(1, (int) app(\App\Settings\BestChangeConfig::class)->position());
            } catch (\Throwable) {
                $global = 1;
            }

            return (string) $global;
        }

        return $raw;
    }
}
