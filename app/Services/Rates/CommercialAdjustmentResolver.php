<?php

declare(strict_types=1);

namespace App\Services\Rates;

use App\Models\DirectionExchange;

/**
 * Single owner-facing commercial adjustment for automatic directions.
 *
 * Admin UI label «Прибыль» always means margin percent:
 *   +1.5 ⇒ customer receives 1.5% less (worse for customer / more margin)
 *   -1.5 ⇒ customer receives 1.5% more (more competitive)
 *
 * Storage mapping (no new DB column):
 *   DERIVED_MARKET_BASELINE → direction_exchange.profit
 *     Canonical fee = -profit
 *   ZELLE_USDTTRC20_BENCHMARK → direction_exchange.floating_fee = -profit
 *     (compiler keeps profit=0; fixed uses floating then fix_fee)
 *   BestChange → direction_exchange.profit
 *     applied via Calculator base path (floating_fee typically 0)
 *
 * Exactly one floating commercial knob — never profit + floating_fee together
 * for the floating quote.
 */
final class CommercialAdjustmentResolver
{
    public const FIELD_PROFIT = 'profit';

    public const FIELD_FLOATING_FEE = 'floating_fee';

    public function family(DirectionExchange $direction): string
    {
        $parser = (string) ($direction->parser_source_name ?? '');
        if ($parser === 'DERIVED_MARKET_BASELINE') {
            return 'DERIVED';
        }
        if ($parser === ZelleUsdUsdtBenchmarkAuthority::PARSER_SOURCE_NAME
            || (int) ($direction->id_currency1 ?? 0) === ZelleUsdBenchmarkResolver::ZELLE_CURRENCY_ID
        ) {
            return 'ZELLE';
        }
        if ($parser === 'BestChange') {
            return 'BESTCHANGE';
        }

        return 'OTHER';
    }

    /**
     * Margin % to show in admin «Прибыль».
     */
    public function displayProfitPercent(DirectionExchange $direction): string
    {
        return match ($this->family($direction)) {
            'ZELLE' => $this->negateFee((string) ($direction->floating_fee ?? '0')),
            default => $this->normalize((string) ($direction->profit ?? '0')),
        };
    }

    /**
     * Which DB column the next admin save must write for floating commercial %.
     */
    public function storageField(DirectionExchange $direction): string
    {
        return $this->family($direction) === 'ZELLE'
            ? self::FIELD_FLOATING_FEE
            : self::FIELD_PROFIT;
    }

    /**
     * Payload fragment for DirectionExchangeProfitController::update.
     * Preserves existing effective rate when the displayed value is re-saved unchanged.
     *
     * @return array<string, mixed>
     */
    public function buildUpdatePayload(DirectionExchange $direction, string $displayProfit): array
    {
        $margin = $this->normalize($displayProfit);
        $family = $this->family($direction);

        if ($family === 'ZELLE') {
            return [
                'profit' => '0',
                'floating_fee' => $this->negateFee($margin),
                'is_type_rate' => 1,
            ];
        }

        if ($family === 'DERIVED') {
            return [
                'profit' => $margin,
                'floating_fee' => '0',
                'is_type_rate' => 1,
            ];
        }

        // BestChange / OTHER: keep profit as the owner knob; do not invent floating_fee.
        return [
            'profit' => $margin,
            'is_type_rate' => 1,
        ];
    }

    public function helpText(DirectionExchange $direction): string
    {
        return match ($this->family($direction)) {
            'ZELLE' => 'Автоматический BASE (USDTTRC20-бенчмарк). «Прибыль»: +6 = клиент получает на 6% меньше (сейчас эквивалент floating_fee=-6). Фиксированный режим дополнительно применяет поле fix_fee (−3).',
            'DERIVED' => 'Автоматический BASE. «Прибыль»: +5 = больше маржа (хуже клиенту), −5 = конкурентнее. Рынок обновляется сам.',
            'BESTCHANGE' => 'BASE из BestChange. «Прибыль»: +5 = больше маржа (хуже клиенту). Не смешивается с floating_fee.',
            default => '«Прибыль» %: + = маржа (хуже клиенту), − = конкурентнее.',
        };
    }

    private function negateFee(string $value): string
    {
        $n = $this->normalize($value);
        if ($n === '0') {
            return '0';
        }
        if (str_starts_with($n, '-')) {
            return substr($n, 1);
        }

        return '-'.$n;
    }

    private function normalize(string $value): string
    {
        $raw = trim($value);
        if ($raw === '') {
            return '0';
        }
        $raw = str_replace([',', ' '], ['.', ''], $raw);
        if (str_starts_with($raw, '+')) {
            $raw = substr($raw, 1);
        }
        if (!is_numeric($raw)) {
            return '0';
        }
        // Trim trailing zeros but keep meaningful scale for ints like 6
        if (str_contains($raw, '.')) {
            $raw = rtrim(rtrim($raw, '0'), '.');
        }
        if ($raw === '' || $raw === '-') {
            return '0';
        }

        return $raw;
    }
}
