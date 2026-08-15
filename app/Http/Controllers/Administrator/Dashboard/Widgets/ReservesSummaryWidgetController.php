<?php

namespace App\Http\Controllers\Administrator\Dashboard\Widgets;

use App\Http\Controllers\Controller;
use App\Models\ReserveTotalSnapshot;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterval;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReservesSummaryWidgetController extends Controller
{
    public function __invoke(): JsonResponse
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
                    'items'              => [],
                ],
            ]);
        }

        $currentTotal = BigDecimal::of((string) $latest->total_usd);

        // Изменения за 1 день и за 7 дней
        $dayChange  = $this->buildChangePayload(CarbonInterval::day(), $currentTotal, $latest);
        $weekChange = $this->buildChangePayload(CarbonInterval::week(), $currentTotal, $latest);

        // Структура текущего резерва по валютам
        $structure = $this->buildCurrentStructure($currentTotal);

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
     * Строит структуру текущего резерва по валютам:
     *  - суммарный резерв по коду валюты
     *  - эквивалент в USD
     *  - долю от общего резерва в USD (процент владения)
     *  - метрики диверсификации.
     */
    protected function buildCurrentStructure(BigDecimal $currentTotal): array
    {
        // Агрегируем резервы по коду валюты.
        // Тут логика упрощённая: summa - black_amount, только активные записи.
        // При необходимости можно добавить свои WHERE/фильтры.
        $rows = DB::table('reserves')
            ->join('code_currency', 'code_currency.id', '=', 'reserves.id_code_currency')
            ->selectRaw('code_currency.name as code, SUM(reserves.summa - COALESCE(reserves.black_amount, 0)) as amount')
            ->where('reserves.status', 1)
            ->groupBy('code_currency.name')
            ->get();

        if ($rows->isEmpty()) {
            return [
                'total_usd'          => $this->formatMoney($currentTotal),
                'total_currencies'   => 0,
                'top_share_percent'  => '0.00',
                'top3_share_percent' => '0.00',
                'herfindahl_index'   => '0.0000',
                'diversification'    => 'unknown',
                'items'              => [],
            ];
        }

        // Собираем внутреннюю структуру с BigDecimal
        $items = [];
        foreach ($rows as $row) {
            $code = (string) $row->code;
            $rawAmount = (string) $row->amount;

            // Если по какой-то причине сумма 0 — пропускаем
            $amount = BigDecimal::of($rawAmount);
            if ($amount->isZero()) {
                continue;
            }

            // Конвертация в USD через ваш общий калькулятор
            // Важно: calculator_converter должен вернуть строку/число, которое можно скормить BigDecimal.
            $amountUsdString = (string) calculator_converter($code, 'USD', $amount->__toString());
            $amountUsd       = BigDecimal::of($amountUsdString);

            $items[] = [
                'code'       => $code,
                'amount'     => $amount,
                'amount_usd' => $amountUsd,
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
                'items'              => [],
            ];
        }

        // Общий текущий резерв по данным валют
        $sumUsd = BigDecimal::zero();
        foreach ($items as $item) {
            /** @var BigDecimal $amountUsd */
            $amountUsd = $item['amount_usd'];
            $sumUsd    = $sumUsd->plus($amountUsd);
        }

        // Для структуры лучше использовать реальный суммарный USD по валютам,
        // чтобы проценты сходились в 100%.
        $totalUsdForStructure = $sumUsd->isZero() ? $currentTotal : $sumUsd;

        // Считаем доли и собираем финальные элементы для ответа
        $shares   = []; // для индексов/топов
        $responseItems = [];

        foreach ($items as $item) {
            /** @var BigDecimal $amount */
            $amount = $item['amount'];
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

            $responseItems[] = [
                'code'           => $item['code'],
                'amount'         => $this->formatMoney($amount, 8), // можно до 8 знаков для крипты
                'amount_usd'     => $this->formatMoney($amountUsd),
                'share_percent'  => $this->formatPercent($share),
                // пока без сложного статуса, при желании можно добавить min/max пороги
                'status'         => 'ok',
            ];
        }

        // Считаем Herfindahl index и топ-доли
        // Herfindahl: сумма квадратов долей (в дробях 0..1)
        $herfindahl = BigDecimal::zero();

        foreach ($shares as $share) {
            /** @var BigDecimal $share */
            $fraction = $share
                ->dividedBy(BigDecimal::of('100'), 8, RoundingMode::HALF_UP); // переводим в 0..1
            $herfindahl = $herfindahl->plus(
                $fraction->multipliedBy($fraction)
            );
        }

        // сортируем доли по убыванию для топов
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
            'total_currencies'   => count($responseItems),
            'top_share_percent'  => $this->formatPercent($top1),
            'top3_share_percent' => $this->formatPercent($top3),
            'herfindahl_index'   => $herfindahl->toScale(4, RoundingMode::HALF_UP)->__toString(),
            'diversification'    => $diversification,
            'items'              => $responseItems,
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
