<?php

namespace App\Services\Reserves;

use App\Models\Reserve;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Log;

class TotalReserveCalculator
{
    /**
     * Считает текущий суммарный резерв во всех валютах, приведённый к USD.
     * Использует группировку по коду валюты.
     */
    public function calculateTotalUsd(): BigDecimal
    {
        $result = $this->calculateGrouped();

        return $result['total_usd'];
    }

    /**
     * Считает суммарный резерв, сначала группируя по коду валюты.
     *
     * Пример:
     *  BTC: 3, BTC: 1, RUB: 2
     *  → BTC = 4 (нативно), RUB = 2 (нативно), далее считаем один раз в USD на каждую валюту.
     *
     * @return array{
     *     total_usd: BigDecimal,
     *     by_currency: array<string, array{
     *         sum: BigDecimal,
     *         sum_usd: BigDecimal|null,
     *         rate: BigDecimal|null
     *     }>
     * }
     */
    public function calculateGrouped(): array
    {
        $reserves = Reserve::query()
            ->where('id_main', 0)
            ->whereHas('currency', function ($query) {
                $query->where('status', 0);
            })
            ->with(['currency.code_currency']) // убрали N+1
            ->orderBy('sorting')
            ->get();




        // 1) Накапливаем суммы по каждому коду валюты
        $byCurrency = [];

        foreach ($reserves as $reserve) {
            if (
                !$reserve->currency ||
                !$reserve->currency->code_currency ||
                empty($reserve->currency->code_currency->name)
            ) {
                continue;
            }

            $code = $reserve->currency->code_currency->name; // Например: BTC, USDT, RUB

            if (! isset($byCurrency[$code])) {
                $byCurrency[$code] = [
                    'sum'     => BigDecimal::zero(), // сумма в нативной валюте (BTC, RUB и т.п.)
                    'sum_usd' => null,               // сюда запишем сумму в USD
                    'rate'    => null,               // сюда можно положить курс к USD (при желании)
                ];
            }

            $byCurrency[$code]['sum'] = $byCurrency[$code]['sum']->plus(
                BigDecimal::of((string) $reserve->summa)
            );
        }

        // 2) Для каждой валюты считаем в USD один раз и собираем общий total_usd
        $totalUsd = BigDecimal::zero();

        foreach ($byCurrency as $code => &$row) {
            try {
                if ($row['sum']->isZero()) {
                    $row['sum_usd'] = BigDecimal::zero();
                    $row['rate']    = BigDecimal::zero();
                    continue;
                }

                // Один вызов конвертера на валюту: вся сумма BTC, вся сумма RUB и т.д.
                $convertedFloat = calculator_converter(
                    $code,          // из какой валюты
                    'USD',          // в USD
                    (string) $row['sum'] // общая сумма по этой валюте
                );

                $sumUsd = BigDecimal::of((string) $convertedFloat);

                // Эффективный курс (не обязательно нужен, но может пригодиться)
                $rate = $row['sum']->isZero()
                    ? BigDecimal::zero()
                    : $sumUsd->dividedBy($row['sum'], 18);

                $row['sum_usd'] = $sumUsd;
                $row['rate']    = $rate;

                $totalUsd = $totalUsd->plus($sumUsd);
            } catch (\Throwable $e) {
//                Log::warning('Failed to convert grouped reserve to USD', [
//                    'currency_code' => $code,
//                    'summa'         => (string) $row['sum'],
//                    'error'         => $e->getMessage(),
//                ]);

                $row['sum_usd'] = BigDecimal::zero();
                $row['rate']    = BigDecimal::zero();
            }
        }
        unset($row);

        return [
            'total_usd'   => $totalUsd,
            'by_currency' => $byCurrency,
        ];
    }
}
