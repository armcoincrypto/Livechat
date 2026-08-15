<?php

namespace iEXPackages\Analytics\Services\Partners;

use App\Models\ReferralStatistics;
use iEXPackages\ReferralSystem\Models\ReferralLink;
use iEXPackages\ReferralSystem\Models\ReferralLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ReferralAnalyticsService
{
    /**
     * Разбор периода из запроса.
     *
     * Поддерживает параметры:
     *  - ?from=YYYY-MM-DD
     *  - ?to=YYYY-MM-DD
     *
     * Если не передано ни from, ни to — используется период последних 30 дней.
     * Время нормализуется до начала/конца дня.
     *
     * @param Request $request
     * @return array [Carbon $from, Carbon $to]
     */
    public function resolvePeriodFromRequest(Request $request): array
    {
        $now = Carbon::now();

        $rawTo = $request->query('to');
        $rawFrom = $request->query('from');

        $to = $rawTo
            ? Carbon::parse($rawTo)->endOfDay()
            : $now->copy()->endOfDay();

        $from = $rawFrom
            ? Carbon::parse($rawFrom)->startOfDay()
            : $to->copy()->subDays(29)->startOfDay();

        // Гарантируем, что from <= to
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    /**
     * Общий summary-блок:
     * - переходы
     * - регистрации
     * - обмены
     * + сравнение с предыдущим таким же периодом (вверх / вниз / равно).
     *
     * @param Carbon $from
     * @param Carbon $to
     * @return array
     */
    public function getSummary(Carbon $from, Carbon $to): array
    {
        // Нормализуем границы периода
        $fromDay = $from->copy()->startOfDay();
        $toDay   = $to->copy()->endOfDay();

        $days = $fromDay->diffInDays($toDay) + 1;

        // Предыдущий период той же длины
        $prevTo = $fromDay->copy()->subDay()->endOfDay();
        $prevFrom = $prevTo->copy()->subDays($days - 1)->startOfDay();

        $current = $this->collectBaseStats($fromDay, $toDay);
        $previous = $this->collectBaseStats($prevFrom, $prevTo);

        return [
            'period' => [
                'from'          => $fromDay->toDateString(),
                'to'            => $toDay->toDateString(),
                'days'          => $days,
                'previous_from' => $prevFrom->toDateString(),
                'previous_to'   => $prevTo->toDateString(),
            ],
            'metrics' => [
                'transitions'   => $this->buildChange($current['transitions'], $previous['transitions']),
                'registrations' => $this->buildChange($current['registrations'], $previous['registrations']),
                'exchanges'     => $this->buildChange($current['exchanges'], $previous['exchanges']),
                'conversion'    => $this->buildConversion($current, $previous),
            ],
        ];
    }

    /**
     * Топ-10 ссылок по количеству переходов за период.
     *
     * Если $from и $to не заданы — считаем за всё время (без ограничения по датам).
     *
     * @param Carbon|null $from
     * @param Carbon|null $to
     * @param int $limit
     * @return array
     */
    public function getTopTransitionLinks(?Carbon $from = null, ?Carbon $to = null, int $limit = 10): array
    {
        $query = ReferralStatistics::query()
            ->from('referral_statistics as rs')
            ->where('rs.is_archive', 0)
            ->when($from && $to, function ($query) use ($from, $to) {
                /** @var \Illuminate\Database\Eloquent\Builder $query */
                $query->whereBetween('rs.created_at', [$from, $to]);
            })
            ->leftJoin('referral_links as rl', 'rs.ref_hash', '=', 'rl.code')
            ->leftJoin('users as u', 'rl.user_id', '=', 'u.id')
            ->selectRaw('
                rs.ref_hash                        as ref_hash,
                COUNT(*)                           as total,
                COUNT(DISTINCT rs.ip_address)      as unique_ips,
                MIN(rs.created_at)                 as first_visit_at,
                MAX(rs.created_at)                 as last_visit_at,
                rl.user_id                         as referral_user_id,
                u.name                             as referral_user_name,
                u.email                            as referral_user_email
            ')
            ->groupBy(
                'rs.ref_hash',
                'rl.user_id',
                'u.name',
                'u.email'
            )
            ->orderByDesc('total')
            ->limit($limit);

        $rows = $query->get();

        $totalTransitions = max(1, (int) $rows->sum('total'));

        return $rows->map(function ($row) use ($totalTransitions) {
            $share = round(($row->total / $totalTransitions) * 100, 1);

            return [
                'ref_hash'             => (string) $row->ref_hash,
                'total'                => (int) $row->total,
                'unique_ips'           => (int) $row->unique_ips,
                'first_visit_at'       => $row->first_visit_at,
                'last_visit_at'        => $row->last_visit_at,
                'referral_user_id'     => $row->referral_user_id !== null ? (int) $row->referral_user_id : null,
                'referral_user_name'   => $row->referral_user_name,
                'referral_user_email'  => $row->referral_user_email,
                'share_percent'        => $share,
            ];
        })->all();
    }

    /**
     * Топ-10 партнёров по количеству обменов и сумме бонусов.
     *
     * Если $from и $to не заданы — считаем за всё время.
     *
     * @param Carbon|null $from
     * @param Carbon|null $to
     * @param int $limit
     * @return array
     */
    public function getTopPartnersByExchanges(?Carbon $from = null, ?Carbon $to = null, int $limit = 10): array
    {
        $query = ReferralLog::query()
            ->from('referral_log as rl')
            ->when($from && $to, function ($query) use ($from, $to) {
                /** @var \Illuminate\Database\Eloquent\Builder $query */
                $query->whereBetween('rl.created_at', [$from, $to]);
            })
            ->leftJoin('users as u', 'rl.id_user', '=', 'u.id')
            ->selectRaw('
                rl.id_user               as partner_id,
                u.name                   as partner_name,
                u.email                  as partner_email,
                COUNT(*)                 as exchanges_count,
                SUM(rl.bonus_number)     as total_earned
            ')
            ->groupBy('rl.id_user', 'u.name', 'u.email')
            ->orderByDesc('exchanges_count')
            ->limit($limit);

        $rows = $query->get();

        $totalExchanges = max(1, (int) $rows->sum('exchanges_count'));
        $totalEarned = max(1.0, (float) $rows->sum('total_earned'));

        return $rows->map(function ($row) use ($totalExchanges, $totalEarned) {
            $shareByExchanges = round(($row->exchanges_count / $totalExchanges) * 100, 1);
            $shareByEarned = round(((float) $row->total_earned / $totalEarned) * 100, 1);

            return [
                'partner_id'       => (int) $row->partner_id,
                'partner_name'     => $row->partner_name,
                'partner_email'    => $row->partner_email,
                'exchanges_count'  => (int) $row->exchanges_count,
                'total_earned'     => (float) $row->total_earned,
                'share_exchanges'  => $shareByExchanges,
                'share_earned'     => $shareByEarned,
            ];
        })->all();
    }

    /**
     * Внутренний метод: сбор базовой статистики за период.
     *
     * Возвращает массив:
     *  - transitions
     *  - registrations
     *  - exchanges
     *
     * @param Carbon $from
     * @param Carbon $to
     * @return array
     */
    protected function collectBaseStats(Carbon $from, Carbon $to): array
    {
        $fromDay = $from->copy()->startOfDay();
        $toDay   = $to->copy()->endOfDay();

        $transitions = ReferralStatistics::query()
            ->where('is_archive', 0)
            ->whereBetween('created_at', [$fromDay, $toDay])
            ->count();

        $registrations = ReferralLink::query()
            ->whereBetween('created_at', [$fromDay, $toDay])
            ->count();

        $exchanges = ReferralLog::query()
            ->whereBetween('created_at', [$fromDay, $toDay])
            ->count();

        return [
            'transitions'   => (int) $transitions,
            'registrations' => (int) $registrations,
            'exchanges'     => (int) $exchanges,
        ];
    }

    /**
     * Универсальная структура для «вверх/вниз/равно» + проценты.
     *
     * @param int $current
     * @param int $previous
     * @return array
     */
    protected function buildChange(int $current, int $previous): array
    {
        $diff = $current - $previous;
        $percent = $previous > 0
            ? round(($diff / $previous) * 100, 1)
            : null;

        $direction = 'same';
        if ($diff > 0) {
            $direction = 'up';
        } elseif ($diff < 0) {
            $direction = 'down';
        }

        return [
            'current'   => $current,
            'previous'  => $previous,
            'diff'      => $diff,
            'percent'   => $percent,
            'direction' => $direction,
        ];
    }

    /**
     * Конверсии: из переходов в регистрации и в обмены.
     *
     * На вход подаются массивы базовых счётчиков:
     *  - transitions
     *  - registrations
     *  - exchanges
     *
     * @param array $current
     * @param array $previous
     * @return array
     */
    protected function buildConversion(array $current, array $previous): array
    {
        $currTrans = max(0, (int) $current['transitions']);
        $currReg   = max(0, (int) $current['registrations']);
        $currExch  = max(0, (int) $current['exchanges']);

        $prevTrans = max(0, (int) $previous['transitions']);
        $prevReg   = max(0, (int) $previous['registrations']);
        $prevExch  = max(0, (int) $previous['exchanges']);

        $currentTransToReg  = $this->safePercent($currReg, $currTrans);
        $previousTransToReg = $this->safePercent($prevReg, $prevTrans);

        $currentTransToExch  = $this->safePercent($currExch, $currTrans);
        $previousTransToExch = $this->safePercent($prevExch, $prevTrans);

        return [
            'transitions_to_registrations' => $this->buildChangeFloat(
                $currentTransToReg,
                $previousTransToReg
            ),
            'transitions_to_exchanges' => $this->buildChangeFloat(
                $currentTransToExch,
                $previousTransToExch
            ),
        ];
    }

    /**
     * Безопасный расчёт процента (num / den * 100) с защитой от деления на 0.
     *
     * @param int $num
     * @param int $den
     * @return float|null
     */
    protected function safePercent(int $num, int $den): ?float
    {
        if ($den <= 0) {
            return null;
        }

        return round(($num / $den) * 100, 1);
    }

    /**
     * Обёртка над ChangeMetric для работы с float-метриками (проценты, средние и т.п.).
     *
     * @param float|null $current
     * @param float|null $previous
     * @return array
     */
    protected function buildChangeFloat(?float $current, ?float $previous): array
    {
        if ($current === null && $previous === null) {
            return [
                'current'   => null,
                'previous'  => null,
                'diff'      => null,
                'percent'   => null,
                'direction' => 'same',
            ];
        }

        $currentValue = $current ?? 0.0;
        $previousValue = $previous ?? 0.0;

        $diff = $currentValue - $previousValue;
        $percent = $previousValue > 0.0
            ? round(($diff / $previousValue) * 100, 1)
            : null;

        $direction = 'same';
        if ($diff > 0) {
            $direction = 'up';
        } elseif ($diff < 0) {
            $direction = 'down';
        }

        return [
            'current'   => $current,
            'previous'  => $previous,
            'diff'      => $diff,
            'percent'   => $percent,
            'direction' => $direction,
        ];
    }

    /**
     * Возвращает подробную статистику по периодам для transitions, registrations, exchanges.
     *
     * Используется для старой агрегированной статистики (total/today/week/month).
     *
     * @return array
     */
    public function getDetailedStatistics(): array
    {
        $transitions = $this->countByPeriodsForStatistics(
            ReferralStatistics::class,
            function ($query) {
                /** @var \Illuminate\Database\Eloquent\Builder $query */
                $query->where('is_archive', 0);
            }
        );

        $registrations = $this->countByPeriodsForStatistics(ReferralLink::class);
        $exchanges = $this->countByPeriodsForStatistics(ReferralLog::class);

        return [
            'transitions'    => $transitions,
            'registrations'  => $registrations,
            'exchanges'      => $exchanges,
        ];
    }

    /**
     * Вспомогательный метод для подсчета статистики по total/today/week/month.
     *
     * @param string $modelClass
     * @param callable|null $constraint
     * @return array
     */
    protected function countByPeriodsForStatistics(string $modelClass, ?callable $constraint = null): array
    {
        /** @var \Illuminate\Database\Eloquent\Builder $base */
        $base = $modelClass::query();

        if ($constraint !== null) {
            $constraint($base);
        }

        $now = Carbon::now();

        $all = (clone $base)->count();
        $today = (clone $base)->whereDate('created_at', $now->toDateString())->count();
        $week = (clone $base)->whereBetween('created_at', [
            $now->copy()->startOfWeek(),
            $now->copy()->endOfWeek(),
        ])->count();
        $month = (clone $base)->whereBetween('created_at', [
            $now->copy()->startOfMonth(),
            $now->copy()->endOfMonth(),
        ])->count();

        return [
            'total' => (int) $all,
            'today' => (int) $today,
            'week'  => (int) $week,
            'month' => (int) $month,
        ];
    }

    /**
     * Глобальный период "за всё время" для summary-блока.
     * Берём минимальную дату среди трёх таблиц и до текущего дня.
     *
     * @return array|null [Carbon $from, Carbon $to] или null если нет данных
     */
    public function resolveGlobalPeriodForSummary(): ?array
    {
        $minStat = ReferralStatistics::query()
            ->where('is_archive', 0)
            ->min('created_at');
        $minReg  = ReferralLink::query()->min('created_at');
        $minEx   = ReferralLog::query()->min('created_at');

        $minDate = collect([$minStat, $minReg, $minEx])
            ->filter()
            ->min();

        if (!$minDate) {
            return null;
        }

        $from = Carbon::parse($minDate)->startOfDay();
        $to   = Carbon::now()->endOfDay();

        return [$from, $to];
    }

    /**
     * Расширенная детальная статистика по выбранному периоду.
     *
     * Даёт:
     * - абсолютные значения (current / previous),
     * - среднее в день,
     * - долю от total "за всё время",
     * - конверсии на основе buildConversion().
     *
     * Структура ответа:
     * [
     *   'period' => [...],
     *   'metrics' => [
     *     'transitions'   => ['counts' => ChangeMetric, 'per_day' => ChangeMetric, 'share_of_all' => ChangeMetric],
     *     'registrations' => [...],
     *     'exchanges'     => [...],
     *     'funnel'        => [
     *         'counts' => [... ChangeMetric по шагам воронки ...],
     *         'steps'  => [
     *             'trans_to_reg'  => ChangeMetric,
     *             'reg_to_exch'   => ChangeMetric,
     *             'trans_to_exch' => ChangeMetric,
     *         ],
     *     ],
     *   ]
     * ]
     *
     * @param Carbon $from
     * @param Carbon $to
     * @return array
     */
    public function getPeriodDetails(Carbon $from, Carbon $to): array
    {
        // Нормализуем период и считаем дни
        $fromDay = $from->copy()->startOfDay();
        $toDay   = $to->copy()->endOfDay();

        $days = $fromDay->diffInDays($toDay) + 1;

        // Период "предыдущий" той же длины, как в getSummary()
        $prevTo = $fromDay->copy()->subDay()->endOfDay();
        $prevFrom = $prevTo->copy()->subDays($days - 1)->startOfDay();

        $current = $this->collectBaseStats($fromDay, $toDay);
        $previous = $this->collectBaseStats($prevFrom, $prevTo);

        $baseCounts = [
            'transitions'   => $this->buildChange($current['transitions'], $previous['transitions']),
            'registrations' => $this->buildChange($current['registrations'], $previous['registrations']),
            'exchanges'     => $this->buildChange($current['exchanges'], $previous['exchanges']),
        ];

        // Общий total "за всё время", чтобы считать долю периода
        $allTransitions = $this->countByPeriodsForStatistics(
            ReferralStatistics::class,
            function ($query) {
                /** @var \Illuminate\Database\Eloquent\Builder $query */
                $query->where('is_archive', 0);
            }
        )['total'];

        $allRegistrations = $this->countByPeriodsForStatistics(ReferralLink::class)['total'];
        $allExchanges = $this->countByPeriodsForStatistics(ReferralLog::class)['total'];

        // Вспомогательная функция: среднее в день по ChangeMetric
        $buildPerDay = function (array $countMetric) use ($days): array {
            $current = $countMetric['current'] ?? null;
            $previous = $countMetric['previous'] ?? null;

            $currPerDay = ($current !== null && $days > 0)
                ? round($current / $days, 2)
                : null;

            $prevPerDay = ($previous !== null && $days > 0)
                ? round($previous / $days, 2)
                : null;

            return $this->buildChangeFloat($currPerDay, $prevPerDay);
        };

        // Вспомогательная функция: доля периода от общего total
        $buildShareOfAllTime = function (array $countMetric, int $allTimeTotal): array {
            $current = $countMetric['current'] ?? null;
            $previous = $countMetric['previous'] ?? null;

            $currShare = ($current !== null && $allTimeTotal > 0)
                ? round(($current / $allTimeTotal) * 100, 2)
                : null;

            $prevShare = ($previous !== null && $allTimeTotal > 0)
                ? round(($previous / $allTimeTotal) * 100, 2)
                : null;

            return $this->buildChangeFloat($currShare, $prevShare);
        };

        // Сохраняем conversion метрики в переменную для переиспользования
        $conversionMetrics = $this->buildConversion($current, $previous);

        // Конверсия регистрация → обмен (в процентах)
        $regCurr = $current['registrations'] ?? 0;
        $regPrev = $previous['registrations'] ?? 0;
        $exCurr  = $current['exchanges'] ?? 0;
        $exPrev  = $previous['exchanges'] ?? 0;

        $currRegToExch = $regCurr > 0
            ? round(($exCurr / $regCurr) * 100, 1)
            : null;

        $prevRegToExch = $regPrev > 0
            ? round(($exPrev / $regPrev) * 100, 1)
            : null;

        $regToExchMetric = $this->buildChangeFloat($currRegToExch, $prevRegToExch);

        // Потери между шагами воронки в абсолютных значениях
        $lossTransToRegCurr = max(0, $current['transitions'] - $current['registrations']);
        $lossTransToRegPrev = max(0, $previous['transitions'] - $previous['registrations']);

        $lossRegToExchCurr  = max(0, $current['registrations'] - $current['exchanges']);
        $lossRegToExchPrev  = max(0, $previous['registrations'] - $previous['exchanges']);

        $lossTransToRegMetric = $this->buildChange($lossTransToRegCurr, $lossTransToRegPrev);
        $lossRegToExchMetric  = $this->buildChange($lossRegToExchCurr, $lossRegToExchPrev);

        // Детализация по каждому типу метрики
        $details = [
            'transitions' => [
                'counts'       => $baseCounts['transitions'],                                      // сколько всего за период
                'per_day'      => $buildPerDay($baseCounts['transitions']),                       // среднее в день
                'share_of_all' => $buildShareOfAllTime($baseCounts['transitions'], $allTransitions), // доля от total
            ],
            'registrations' => [
                'counts'       => $baseCounts['registrations'],
                'per_day'      => $buildPerDay($baseCounts['registrations']),
                'share_of_all' => $buildShareOfAllTime($baseCounts['registrations'], $allRegistrations),
            ],
            'exchanges' => [
                'counts'       => $baseCounts['exchanges'],
                'per_day'      => $buildPerDay($baseCounts['exchanges']),
                'share_of_all' => $buildShareOfAllTime($baseCounts['exchanges'], $allExchanges),
            ],
            // Глубина воронки по шагам
            'funnel' => [
                'counts' => [
                    'transitions'   => $baseCounts['transitions'],
                    'registrations' => $baseCounts['registrations'],
                    'exchanges'     => $baseCounts['exchanges'],
                ],
                'steps'  => [
                    'trans_to_reg'  => $conversionMetrics['transitions_to_registrations'] ?? null,
                    'reg_to_exch'   => $regToExchMetric,
                    'trans_to_exch' => $conversionMetrics['transitions_to_exchanges'] ?? null,
                ],
                'drops' => [
                    'trans_to_reg'  => $lossTransToRegMetric,
                    'reg_to_exch'   => $lossRegToExchMetric,
                ],
            ],
        ];

        return [
            'period' => [
                'from'          => $fromDay->toDateString(),
                'to'            => $toDay->toDateString(),
                'days'          => $days,
                'previous_from' => $prevFrom->toDateString(),
                'previous_to'   => $prevTo->toDateString(),
            ],
            'metrics' => $details,
        ];
    }
}
