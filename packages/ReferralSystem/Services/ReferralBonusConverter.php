<?php

declare(strict_types=1);

namespace iEXPackages\ReferralSystem\Services;

use App\Settings\ReferralConfig;
use Illuminate\Support\Str;

class ReferralBonusConverter
{
    public function __construct(
        private readonly ReferralConfig $config,
    ) {}

    public function convertGiveToBonus(
        string $fromCode,
        string $bonusCode,
        float $amount,
        ?float $internalRate = null
    ): float {
        $from = Str::upper(trim($fromCode));
        $to   = Str::upper(trim($bonusCode));

        if ($from === '' || $to === '' || $amount <= 0.0 || !is_finite($amount)) {
            return 0.0;
        }

        // 0) одинаковые валюты
        if ($from === $to) {
            return $amount;
        }

        // 1) internal_rate — приоритетный ручной коэффициент
        if ($this->isValidRate($internalRate)) {
            return $amount * (float) $internalRate;
        }

        $fallback = $this->config->referralFallbackCodeCurrencies();

        // 2) 1:1 внутри fallback-группы
        if ($fallback !== [] && in_array($from, $fallback, true) && in_array($to, $fallback, true)) {
            return $amount;
        }

        // 3) сначала пробуем прямую конвертацию
        $direct = calculator_converter($from, $to, $amount);
        if ($direct > 0.0 && is_finite($direct)) {
            return $direct;
        }

        // 4) если прямой пары нет — пробуем через цепочку:
        //    from -> target -> to, где target из приоритетного списка
        foreach ($this->priorityTargets($to, $fallback) as $target) {
            // from -> target
            $first = calculator_converter($from, $target, $amount);
            if ($first <= 0.0 || !is_finite($first)) {
                continue;
            }

            // target == to уже подходит
            if ($target === $to) {
                return $first;
            }

            // target -> to
            $second = calculator_converter($target, $to, $first);
            if ($second > 0.0 && is_finite($second)) {
                return $second;
            }
        }

        return 0.0;
    }

    /**
     * Приоритет: сначала целевая валюта, затем fallback-группа (кроме целевой).
     */
    private function priorityTargets(string $to, array $fallback): array
    {
        $list = [$to];

        foreach ($fallback as $code) {
            $code = strtoupper(trim($code));
            if ($code !== '' && $code !== $to) {
                $list[] = $code;
            }
        }

        return array_values(array_unique($list));
    }

    private function isValidRate(mixed $rate): bool
    {
        return is_numeric($rate) && is_finite((float) $rate) && (float) $rate > 0.0;
    }
}
