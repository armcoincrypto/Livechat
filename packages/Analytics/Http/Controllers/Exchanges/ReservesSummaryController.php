<?php

namespace iEXPackages\Analytics\Http\Controllers\Exchanges;

use App\Http\Controllers\Controller;
use App\Models\Reserve;
use App\Models\ReserveTotalSnapshot;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReservesSummaryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Последний доступный снапшот (актуальный total резерва в USD)
        /** @var \App\Models\ReserveTotalSnapshot|null $latest */
        $latest = ReserveTotalSnapshot::query()
            ->orderByDesc('snapshot_at')
            ->first();

        if (!$latest) {
            // Если пока нет данных — возвращаем нули
            return response()->json([
                'total_usd'  => '0.00',
                'day'        => null,
                'week'       => null,
                'structure'  => [
                    'total_usd'          => '0.00',
                    'total_currencies'   => 0,
                    'top_share_percent'  => '0.00',
                    'top3_share_percent' => '0.00',
                    'herfindahl_index'   => '0.0000',
                    'diversification'    => 'unknown',
                    'min_reserve'        => null,
                    'max_reserve'        => null,
                    'negative_reserves'  => [
                        'count'     => 0,
                        'total_usd' => '0.00',
                    ],
                    'pagination'         => [
                        'page'      => 1,
                        'per_page'  => 30,
                        'total'     => 0,
                        'last_page' => 1,
                    ],
                    'items'              => [],
                ],
            ]);
        }

        $currentTotal = BigDecimal::of((string) $latest->total_usd);

        // Изменения за 1 день и за 7 дней
        $dayChange  = $this->buildChangePayload(CarbonInterval::day(), $currentTotal, $latest);
        $weekChange = $this->buildChangePayload(CarbonInterval::week(), $currentTotal, $latest);

        // Фильтр по знаку резерва: positive | negative | null (все)
        $reserveSign = $request->query('reserve_sign');
        if (! in_array($reserveSign, ['positive', 'negative'], true)) {
            $reserveSign = null;
        }

        // Сортировка
        $allowedSortBy = ['amount_usd', 'share_percent', 'days_since_update', 'code', 'payment_name'];
        $sortBy = $request->query('sort_by', 'share_percent');
        if (! in_array($sortBy, $allowedSortBy, true)) {
            $sortBy = 'share_percent';
        }

        $sortDir = strtolower((string) $request->query('sort_dir', 'desc'));
        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'desc';
        }

        // Пагинация
        $page = (int) $request->query('page', 1);
        if ($page < 1) {
            $page = 1;
        }

        $perPage = (int) $request->query('per_page', 30);
        if ($perPage < 1) {
            $perPage = 30;
        } elseif ($perPage > 200) {
            $perPage = 200;
        }

        // Структура текущего резерва по валютам
        $structure = $this->buildCurrentStructure($currentTotal, $reserveSign, $sortBy, $sortDir, $page, $perPage);

        return response()->json([
            'total_usd' => $this->formatMoney($currentTotal),
            'day'       => $dayChange,
            'week'      => $weekChange,
            'structure' => $structure,
        ]);
    }

    /**
     * Собирает данные об изменении за указанный период.
     */
    protected function buildChangePayload(
        CarbonInterval $interval,
        BigDecimal $currentTotal,
        ReserveTotalSnapshot $latestSnapshot
    ): ?array {
        $targetTime = now()->sub($interval);

        /** @var \App\Models\ReserveTotalSnapshot|null $pastSnapshot */
        $pastSnapshot = ReserveTotalSnapshot::query()
            ->where('snapshot_at', '<=', $targetTime)
            ->orderByDesc('snapshot_at')
            ->first();

        // Если нет снапшота в прошлом — считаем, что изменений нет, но отдаем структуру
        if (!$pastSnapshot) {
            $diff    = BigDecimal::zero();
            $percent = BigDecimal::zero();

            return [
                'diff'       => $this->formatMoney($diff),
                'percent'    => $this->formatPercent($percent),
                'current_at' => $latestSnapshot->snapshot_at->toDateTimeString(),
                'past_at'    => null,
            ];
        }

        $pastTotal = BigDecimal::of((string) $pastSnapshot->total_usd);
        $diff      = $currentTotal->minus($pastTotal);

        if ($pastTotal->isZero()) {
            $percent = BigDecimal::zero();
        } else {
            // 8 знаков для расчёта, с округлением, потом округлим до 2 при выводе
            $percent = $diff
                ->dividedBy($pastTotal, 8, RoundingMode::HALF_UP)
                ->multipliedBy(100);
        }

        return [
            'diff'       => $this->formatMoney($diff),
            'percent'    => $this->formatPercent($percent),
            'current_at' => $latestSnapshot->snapshot_at->toDateTimeString(),
            'past_at'    => $pastSnapshot->snapshot_at->toDateTimeString(),
        ];
    }

    /**
     * Строит структуру текущего резерва:
     *  - каждый резерв отдельной строкой (без группировки по коду)
     *  - эквивалент в USD
     *  - доля от общего резерва в USD
     *  - метрики диверсификации по всем строкам.
     *
     * @param  BigDecimal     $currentTotal  Общий резерв (из снапшота)
     * @param  string|null    $reserveSign   Фильтр по знаку: 'positive' | 'negative' | null (все)
     * @param  string         $sortBy        Поле сортировки: amount_usd|share_percent|days_since_update|code|payment_name
     * @param  string         $sortDir       Направление сортировки: asc|desc
     * @param  int            $page          Номер страницы (>=1)
     * @param  int            $perPage       Кол-во записей на странице
     */
    protected function buildCurrentStructure(
        BigDecimal $currentTotal,
        ?string $reserveSign = null,
        string $sortBy = 'share_percent',
        string $sortDir = 'desc',
        int $page = 1,
        int $perPage = 30
    ): array
    {
        // Берём все активные резервы вместе с валютой, кодом валюты и платежкой
        $reserves = Reserve::query()
            ->where('status', 0) // если у тебя активный статус = 1 — поменяй тут
            ->with(['currency.code_currency', 'currency.payment'])
            ->get();

        if ($reserves->isEmpty()) {
            return [
                'total_usd'          => $this->formatMoney($currentTotal),
                'total_currencies'   => 0,
                'top_share_percent'  => '0.00',
                'top3_share_percent' => '0.00',
                'herfindahl_index'   => '0.0000',
                'diversification'    => 'unknown',
                'min_reserve'        => null,
                'max_reserve'        => null,
                'negative_reserves'  => [
                    'count'     => 0,
                    'total_usd' => '0.00',
                ],
                'items'              => [],
            ];
        }

        $items = [];

// Собираем ID валют, по которым есть резервы
        $currencyIds = $reserves
            ->pluck('id_currency')
            ->filter()
            ->unique()
            ->values()
            ->all();

// Если есть ID, считаем средние расходы по валютам за 7 и 30 дней
        // Если есть ID, считаем средние и суммарные расходы по валютам за 7 и 30 дней
        $avgOut7ByCurrency   = [];
        $avgOut30ByCurrency  = [];
        $sumOut7ByCurrency   = [];
        $sumOut30ByCurrency  = [];

        if (! empty($currencyIds)) {
            $today   = now()->startOfDay();
            $from7   = $today->copy()->subDays(6);   // последние 7 дней (включая сегодня)
            $from30  = $today->copy()->subDays(29);  // последние 30 дней

            // 7 дней
            $rows7 = DB::table('currencies_analytics_daily')
                ->selectRaw('id_currency, SUM(out_amount_usd) as sum_out_usd, COUNT(DISTINCT date) as days_count')
                ->whereIn('id_currency', $currencyIds)
                ->whereBetween('date', [$from7->toDateString(), $today->toDateString()])
                ->groupBy('id_currency')
                ->get();

            foreach ($rows7 as $row) {
                $daysCount  = (int) ($row->days_count ?? 0);
                $sumOut     = BigDecimal::of((string) $row->sum_out_usd);
                $currencyId = (int) $row->id_currency;

                // суммарный расход за 7 дней
                $sumOut7ByCurrency[$currencyId] = $sumOut;

                if ($daysCount > 0) {
                    $avgOut7ByCurrency[$currencyId] = $sumOut
                        ->dividedBy($daysCount, 8, RoundingMode::HALF_UP);
                }
            }

            // 30 дней
            $rows30 = DB::table('currencies_analytics_daily')
                ->selectRaw('id_currency, SUM(out_amount_usd) as sum_out_usd, COUNT(DISTINCT date) as days_count')
                ->whereIn('id_currency', $currencyIds)
                ->whereBetween('date', [$from30->toDateString(), $today->toDateString()])
                ->groupBy('id_currency')
                ->get();

            foreach ($rows30 as $row) {
                $daysCount  = (int) ($row->days_count ?? 0);
                $sumOut     = BigDecimal::of((string) $row->sum_out_usd);
                $currencyId = (int) $row->id_currency;

                // суммарный расход за 30 дней
                $sumOut30ByCurrency[$currencyId] = $sumOut;

                if ($daysCount > 0) {
                    $avgOut30ByCurrency[$currencyId] = $sumOut
                        ->dividedBy($daysCount, 8, RoundingMode::HALF_UP);
                }
            }
        }

        /** @var \App\Models\Reserve $reserve */
        foreach ($reserves as $reserve) {
            $currency     = $reserve->currency;
            $codeCurrency = $currency?->code_currency;

            // если нет привязки к валюте/коду — пропускаем
            if (! $currency || ! $codeCurrency) {
                continue;
            }

            $code = (string) $codeCurrency->name;

            // считаем amount по конкретному резерву: summa - black_amount
            $summa       = BigDecimal::of((string) ($reserve->summa ?? '0'));
            $blackAmount = BigDecimal::of((string) ($reserve->black_amount ?? '0'));
            $amount      = $summa->minus($blackAmount);

            if ($amount->isZero()) {
                continue;
            }

            // Конвертация в USD через общий калькулятор
            $amountUsdString = (string) calculator_converter($code, 'USD', $amount->__toString());
            $amountUsd       = BigDecimal::of($amountUsdString);

// Определяем знак резерва
            $sign = 'zero';
            if ($amountUsd->isGreaterThan(BigDecimal::zero())) {
                $sign = 'positive';
            } elseif ($amountUsd->isLessThan(BigDecimal::zero())) {
                $sign = 'negative';
            }

// Возраст / активность резерва
// Берём updated_at, если есть, иначе created_at
            $timestamp = $reserve->updated_at ?? $reserve->created_at;
            /** @var \Carbon\Carbon|null $timestamp */
            $daysSinceUpdate = null;
            $isStale         = false;
            $lastUpdatedAt   = null;

            if ($timestamp instanceof Carbon) {
                $daysSinceUpdate = $timestamp->diffInDays(now());
                $lastUpdatedAt   = $timestamp->toDateTimeString();
                // Порог "давно не трогали": условно 30 дней, при желании можно вынести в настройки
                $isStale = $daysSinceUpdate > 30;
            }

// Дни обеспеченности (coverage)
// Берём средний расход по id_currency из предварительно посчитанных массивов
            $currencyId = (int) ($reserve->id_currency ?? 0);

            $coverage7  = null;
            $coverage30 = null;

            // Liquidity Efficiency (по 30-дневному обороту)
// Насколько активно используется резерв: суммарный расход за 30 дней / текущий резерв
            $liquidityEfficiency30 = null;
            if ($amountUsd->isGreaterThan(BigDecimal::zero()) && isset($sumOut30ByCurrency[$currencyId])) {
                $sumOut30 = $sumOut30ByCurrency[$currencyId];
                if (! $sumOut30->isZero()) {
                    $liquidityEfficiency30 = $sumOut30
                        ->dividedBy($amountUsd, 8, RoundingMode::HALF_UP);
                }
            }

// coverage считаем только для положительного резерва и если есть средний расход
            if ($amountUsd->isGreaterThan(BigDecimal::zero())) {
                if (isset($avgOut7ByCurrency[$currencyId])) {
                    $avgOut7 = $avgOut7ByCurrency[$currencyId];
                    if (! $avgOut7->isZero()) {
                        $coverage7 = $amountUsd
                            ->dividedBy($avgOut7, 4, RoundingMode::HALF_UP);
                    }
                }

                if (isset($avgOut30ByCurrency[$currencyId])) {
                    $avgOut30 = $avgOut30ByCurrency[$currencyId];
                    if (! $avgOut30->isZero()) {
                        $coverage30 = $amountUsd
                            ->dividedBy($avgOut30, 4, RoundingMode::HALF_UP);
                    }
                }
            }

            $items[] = [
                'code'                    => $code,
                'code_currency_name'      => (string) ($codeCurrency->name ?? ''),           // название валюты
                'payment_name'            => (string) ($currency->payment->name ?? ''),      // название ПС
                'amount'                  => $amount,
                'amount_usd'              => $amountUsd,
                'last_updated_at'         => $lastUpdatedAt,
                'days_since_update'       => $daysSinceUpdate,
                'is_stale'                => $isStale,
                'sign'                    => $sign,
                'coverage_days_7'         => $coverage7 ? $coverage7 : null,
                'coverage_days_30'        => $coverage30 ? $coverage30 : null,
                'liquidity_efficiency_30' => $liquidityEfficiency30 ? $liquidityEfficiency30 : null,
            ];
        }

        if (empty($items)) {
            return [
                'total_usd'          => $this->formatMoney($currentTotal),
                'total_currencies'   => 0,
                'top_share_percent'  => '0.00',
                'top3_share_percent' => '0.00',
                'herfindahl_index'   => '0.0000',
                'diversification'    => 'unknown',
                'min_reserve'        => null,
                'max_reserve'        => null,
                'negative_reserves'  => [
                    'count'     => 0,
                    'total_usd' => '0.00',
                ],
                'items'              => [],
            ];
        }

        // Общий текущий резерв (в USD) по всем резервам
        $sumUsd = BigDecimal::zero();
        foreach ($items as $item) {
            /** @var BigDecimal $amountUsd */
            $amountUsd = $item['amount_usd'];
            $sumUsd    = $sumUsd->plus($amountUsd);
        }

        $totalUsdForStructure = $sumUsd->isZero() ? $currentTotal : $sumUsd;

        // Считаем доли и собираем финальные элементы для ответа
        $shares        = [];
        $responseItems = [];

        // Для минимального / максимального резерва (только положительные резервы)
        $minUsd      = null;
        $maxUsd      = null;
        $minItemInfo = null;
        $maxItemInfo = null;

        // Для отрицательных резервов
        $negativeCount    = 0;
        $negativeTotalUsd = BigDecimal::zero();

        foreach ($items as $item) {
            /** @var BigDecimal $amount */
            $amount    = $item['amount'];
            /** @var BigDecimal $amountUsd */
            $amountUsd = $item['amount_usd'];

            if ($totalUsdForStructure->isZero()) {
                $share = BigDecimal::zero();
            } else {
                $share = $amountUsd
                    ->dividedBy($totalUsdForStructure, 8, RoundingMode::HALF_UP)
                    ->multipliedBy(100);
            }

            $shares[] = $share;

            // Отрицательные резервы считаем отдельно
            if ($amountUsd->isLessThan(BigDecimal::zero())) {
                $negativeCount++;
                $negativeTotalUsd = $negativeTotalUsd->plus($amountUsd);
            } elseif ($amountUsd->isGreaterThan(BigDecimal::zero())) {
                // Обновляем минимальный / максимальный резерв ТОЛЬКО для положительных значений
                if ($minUsd === null || $amountUsd->isLessThan($minUsd)) {
                    $minUsd = $amountUsd;
                    $minItemInfo = [
                        'code'               => $item['code'],
                        'code_currency_name' => $item['code_currency_name'],
                        'payment_name'       => $item['payment_name'],
                        'amount_usd'         => $amountUsd,
                    ];
                }

                if ($maxUsd === null || $amountUsd->isGreaterThan($maxUsd)) {
                    $maxUsd = $amountUsd;
                    $maxItemInfo = [
                        'code'               => $item['code'],
                        'code_currency_name' => $item['code_currency_name'],
                        'payment_name'       => $item['payment_name'],
                        'amount_usd'         => $amountUsd,
                    ];
                }
            }

            $responseItems[] = [
                'code'               => $item['code'],
                'code_currency_name' => $item['code_currency_name'],
                'payment_name'       => $item['payment_name'],
                'amount'             => $this->formatMoney($amount, 8),       // до 8 знаков для крипты
                'amount_usd'         => $this->formatMoney($amountUsd),
                'share_percent'      => $this->formatPercent($share),
                'last_updated_at'    => $item['last_updated_at'],
                'days_since_update'  => $item['days_since_update'],
                'is_stale'           => $item['is_stale'],
                'sign'               => $item['sign'],
                'is_positive'        => $item['sign'] === 'positive',
                'is_negative'        => $item['sign'] === 'negative',
                'coverage_days_7'    => $item['coverage_days_7']
                    ? $item['coverage_days_7']->toScale(1, RoundingMode::HALF_UP)->__toString()
                    : null,
                'coverage_days_30'   => $item['coverage_days_30']
                    ? $item['coverage_days_30']->toScale(1, RoundingMode::HALF_UP)->__toString()
                    : null,
                'liquidity_efficiency_30' => $item['liquidity_efficiency_30']
                    ? $item['liquidity_efficiency_30']->toScale(2, RoundingMode::HALF_UP)->__toString()
                    : null,
                'status'             => 'ok',
            ];
        }

// Применяем фильтр по знаку к списку элементов (но не к метрикам)
        if ($reserveSign === 'positive') {
            $responseItems = array_values(array_filter($responseItems, static function (array $row): bool {
                return $row['is_positive'] === true;
            }));
        } elseif ($reserveSign === 'negative') {
            $responseItems = array_values(array_filter($responseItems, static function (array $row): bool {
                return $row['is_negative'] === true;
            }));
        }

// Сортировка по полю sortBy
        usort($responseItems, static function (array $a, array $b) use ($sortBy, $sortDir): int {
            $av = $a[$sortBy] ?? null;
            $bv = $b[$sortBy] ?? null;

            // null всегда в конце
            if ($av === null && $bv === null) {
                return 0;
            }
            if ($av === null) {
                return 1;
            }
            if ($bv === null) {
                return -1;
            }

            // Числовые поля
            if (is_numeric($av) && is_numeric($bv)) {
                $av = (float) $av;
                $bv = (float) $bv;
            } else {
                $av = (string) $av;
                $bv = (string) $bv;
            }

            $cmp = $av <=> $bv;

            return $sortDir === 'desc' ? -$cmp : $cmp;
        });

// Пагинация по отсортированному списку
        $totalItems = count($responseItems);
        $lastPage   = (int) max((int) ceil($totalItems / $perPage), 1);
        if ($page > $lastPage) {
            $page = $lastPage;
        }
        $offset         = ($page - 1) * $perPage;
        $paginatedItems = array_slice($responseItems, $offset, $perPage);

// Индекс Херфиндаля и топ-доли
        $herfindahl = BigDecimal::zero();

        foreach ($shares as $share) {
            /** @var BigDecimal $share */
            $fraction = $share
                ->dividedBy(BigDecimal::of('100'), 8, RoundingMode::HALF_UP); // 0..1
            $herfindahl = $herfindahl->plus(
                $fraction->multipliedBy($fraction)
            );
        }

        usort($shares, function (BigDecimal $a, BigDecimal $b) {
            return $b->compareTo($a);
        });

        $top1 = $shares[0] ?? BigDecimal::zero();
        $top3 = BigDecimal::zero();

        foreach (array_slice($shares, 0, 3) as $s) {
            /** @var BigDecimal $s */
            $top3 = $top3->plus($s);
        }

        $diversification = $this->resolveDiversificationLevel($herfindahl);

        return [
            'total_usd'          => $this->formatMoney($totalUsdForStructure),
            'total_currencies'   => $totalItems, // общее кол-во строк после фильтров, до пагинации
            'top_share_percent'  => $this->formatPercent($top1),
            'top3_share_percent' => $this->formatPercent($top3),
            'herfindahl_index'   => $herfindahl->toScale(4, RoundingMode::HALF_UP)->__toString(),
            'diversification'    => $diversification,
            'min_reserve'        => $minItemInfo ? [
                'code'               => $minItemInfo['code'],
                'code_currency_name' => $minItemInfo['code_currency_name'],
                'payment_name'       => $minItemInfo['payment_name'],
                'amount_usd'         => $this->formatMoney($minItemInfo['amount_usd']),
            ] : null,
            'max_reserve'        => $maxItemInfo ? [
                'code'               => $maxItemInfo['code'],
                'code_currency_name' => $maxItemInfo['code_currency_name'],
                'payment_name'       => $maxItemInfo['payment_name'],
                'amount_usd'         => $this->formatMoney($maxItemInfo['amount_usd']),
            ] : null,
            'negative_reserves'  => [
                'count'     => $negativeCount,
                'total_usd' => $this->formatMoney($negativeTotalUsd),
            ],
            'pagination'         => [
                'page'      => $page,
                'per_page'  => $perPage,
                'total'     => $totalItems,
                'last_page' => $lastPage,
            ],
            'items'              => $paginatedItems,
        ];
    }

    /**
     * Превращаем индекс Херфиндаля в простую текстовую метку.
     */
    protected function resolveDiversificationLevel(BigDecimal $herfindahl): string
    {
        // чем меньше индекс — тем больше диверсификация
        $low    = BigDecimal::of('0.15'); // < 0.15 — высокая диверсификация
        $medium = BigDecimal::of('0.25'); // < 0.25 — средняя, дальше — высокая концентрация

        if ($herfindahl->compareTo($low) < 0) {
            return 'high'; // хорошо диверсифицирован
        }

        if ($herfindahl->compareTo($medium) < 0) {
            return 'medium';
        }

        return 'low'; // высокая концентрация
    }

    protected function formatMoney(BigDecimal $value, int $scale = 2): string
    {
        return $value->toScale($scale, RoundingMode::HALF_UP)->__toString();
    }

    protected function formatPercent(BigDecimal $value, int $scale = 2): string
    {
        return $value->toScale($scale, RoundingMode::HALF_UP)->__toString();
    }
}
