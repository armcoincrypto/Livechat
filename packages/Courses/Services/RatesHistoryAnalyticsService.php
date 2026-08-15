<?php

namespace iEXPackages\Courses\Services;

use Carbon\Carbon;
use iEXPackages\Courses\Models\RatesHistoryLog;
use Illuminate\Support\Collection;

/**
 * Сервис небольшой аналитики по истории изменения курсов.
 */
class RatesHistoryAnalyticsService
{
    /**
     * Возвращает сводную аналитику для одного парсера.
     *
     * @param int    $parserId  ID парсера (id_source в таблице logs)
     * @param string $source    Строковый идентификатор источника (по умолчанию 'source',
     *                          т.к. мы так передаём в RatesLoggerService::batchLog).
     *
     * @return array<string, mixed>
     */
    public function getParserSummary(int $parserId, string $source = 'source'): array
    {
        $baseQuery = RatesHistoryLog::query()
            ->where('source', $source)
            ->where('id_source', $parserId);

        $totalChanges = $baseQuery->count();

        if ($totalChanges === 0) {
            return [
                'total_changes'        => 0,
                'changes_24h'          => 0,
                'last_change_at'       => null,
                'last_rate'            => null,
                'prev_rate'            => null,
                'last_abs_change'      => null,
                'last_percent_change'  => null,
                'min_rate_24h'         => null,
                'max_rate_24h'         => null,
                'avg_rate_24h'         => null,
                'volatility_24h'       => null,
            ];
        }

        // Последний лог и предыдущий
        /** @var RatesHistoryLog|null $lastLog */
        $lastLog = (clone $baseQuery)
            ->orderByDesc('created_at')
            ->first();

        /** @var RatesHistoryLog|null $prevLog */
        $prevLog = (clone $baseQuery)
            ->where('id', '<', $lastLog->id)
            ->orderByDesc('created_at')
            ->first();

        $lastRate  = $this->toNumeric($lastLog?->new_value);
        $prevRate  = $this->toNumeric($prevLog?->new_value);
        $absChange = null;
        $percentChange = null;

        if ($lastRate !== null && $prevRate !== null) {
            $absChange = $this->bcSubSafe($lastRate, $prevRate, 18);
            if (bccomp($prevRate, '0', 18) !== 0) {
                $percentChange = $this->bcMulSafe(
                    $this->bcDivSafe($absChange, $prevRate, 8),
                    '100',
                    2
                );
            }
        }

        // Логи за последние 24 часа
        $since = Carbon::now()->subDay();

        /** @var Collection<int,RatesHistoryLog> $logs24 */
        $logs24 = (clone $baseQuery)
            ->where('created_at', '>=', $since)
            ->orderBy('created_at')
            ->get();

        $changes24 = $logs24->count();

        $minRate24 = null;
        $maxRate24 = null;
        $avgRate24 = null;
        $volatility24 = null;

        if ($changes24 > 0) {
            $numericRates = $logs24
                ->map(fn (RatesHistoryLog $log) => $this->toNumeric($log->new_value))
                ->filter(fn (?string $v) => $v !== null)
                ->values();

            if ($numericRates->isNotEmpty()) {
                $minRate24 = $numericRates->reduce(function (?string $carry, string $item) {
                    if ($carry === null) {
                        return $item;
                    }
                    return bccomp($item, $carry, 18) < 0 ? $item : $carry;
                });

                $maxRate24 = $numericRates->reduce(function (?string $carry, string $item) {
                    if ($carry === null) {
                        return $item;
                    }
                    return bccomp($item, $carry, 18) > 0 ? $item : $carry;
                });

                // Среднее = сумма / количество
                $sum = '0';
                foreach ($numericRates as $value) {
                    $sum = $this->bcAddSafe($sum, $value, 18);
                }

                $avgRate24 = $this->bcDivSafe($sum, (string) $numericRates->count(), 18);

                if ($minRate24 !== null && $maxRate24 !== null) {
                    $volatility24 = $this->bcSubSafe($maxRate24, $minRate24, 18);
                }
            }
        }

        return [
            'total_changes'        => $totalChanges,
            'changes_24h'          => $changes24,
            'last_change_at'       => $lastLog?->created_at?->toIso8601String(),
            'last_rate'            => $lastRate,
            'prev_rate'            => $prevRate,
            'last_abs_change'      => $absChange,
            'last_percent_change'  => $percentChange,
            'min_rate_24h'         => $minRate24,
            'max_rate_24h'         => $maxRate24,
            'avg_rate_24h'         => $avgRate24,
            'volatility_24h'       => $volatility24,
        ];
    }

    /**
     * Преобразует строковое значение в числовую строку или null.
     */
    protected function toNumeric(?string $value): ?string
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        return (string) $value;
    }

    /**
     * Безопасное bcadd с обработкой ошибок.
     */
    protected function bcAddSafe(string $left, string $right, int $scale = 18): string
    {
        return bcadd($left, $right, $scale);
    }

    /**
     * Безопасное bcsub с обработкой ошибок.
     */
    protected function bcSubSafe(string $left, string $right, int $scale = 18): string
    {
        return bcsub($left, $right, $scale);
    }

    /**
     * Безопасное bcdiv: если делитель 0 — возвращаем '0'.
     */
    protected function bcDivSafe(string $left, string $right, int $scale = 18): string
    {
        if ($right === '0' || bccomp($right, '0', $scale) === 0) {
            return '0';
        }

        return bcdiv($left, $right, $scale);
    }

    /**
     * Безопасное bcmul с обработкой ошибок.
     */
    protected function bcMulSafe(string $left, string $right, int $scale = 18): string
    {
        return bcmul($left, $right, $scale);
    }
}
