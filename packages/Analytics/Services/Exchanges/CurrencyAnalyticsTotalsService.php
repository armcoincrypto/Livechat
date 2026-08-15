<?php

namespace iEXPackages\Analytics\Services\Exchanges;


use App\Models\Currency;
use App\Models\CurrencyAnalytics;
use App\Models\CurrencyAnalyticsDaily;
use Brick\Math\BigDecimal;
use Carbon\Carbon;

class CurrencyAnalyticsTotalsService
{
    public const MODE_FIXED   = 'fixed';
    public const MODE_CURRENT = 'current';
    /**
     * Получить общую сумму обменов по системе на основе аналитики валют.
     *
     * Режимы:
     *  - mode = fixed   — используем сохранённые *_usd поля в CurrencyAnalytics;
     *  - mode = current — считаем по текущему курсу через convert_to_usd().
     *
     * @param string|null $mode 'fixed' | 'current' | null
     * @return array{
     *     mode: string,
     *     in: string,
     *     out: string,
     *     total: string,
     *     currencies?: array<int, array{
     *          currency_id:int,
     *          code:string,
     *          in_usd:string,
     *          out_usd:string,
     *          total_usd:string
     *     }>
     * }
     */
    public function getTotals(?string $mode = null): array
    {
        $resolvedMode = $this->resolveMode($mode);

        if ($resolvedMode === self::MODE_FIXED) {
            return $this->getTotalsFixed();
        }

        return $this->getTotalsCurrent();
    }

    /**
     * Определяет режим расчёта на основе параметра и глобальной настройки.
     */
    protected function resolveMode(?string $mode): string
    {
        if ($mode === self::MODE_FIXED || $mode === self::MODE_CURRENT) {
            return $mode;
        }

        // 0 — считать по текущему курсу (convert_to_usd)
        // 1 — использовать зафиксированные USD-значения из *_usd
        $setting = (int) iEXSetting('wa_currency_exchange_type');

        return $setting === 1 ? self::MODE_FIXED : self::MODE_CURRENT;
    }

    /**
     * Режим "fixed": считаем по полям in_amount_usd / out_amount_usd в CurrencyAnalytics.
     * Всё делается на стороне БД одним запросом + опциональная детализация по валютам.
     */
    protected function getTotalsFixed(): array
    {
        // Общие суммы по системе
        $row = CurrencyAnalytics::query()
            ->join('currencies', 'currencies_analytics.id_currency', '=', 'currencies.id')
            ->where('currencies.status', '!=', 2)           // active / enabled
            ->where('currencies.is_archive', '=', 0)        // при необходимости учитываем только активные
            ->selectRaw('COALESCE(SUM(in_amount_usd), 0)  as in_amount_usd')
            ->selectRaw('COALESCE(SUM(out_amount_usd), 0) as out_amount_usd')
            ->first();

        $in  = BigDecimal::of((string) ($row->in_amount_usd ?? '0'));
        $out = BigDecimal::of((string) ($row->out_amount_usd ?? '0'));

        $perCurrency = CurrencyAnalytics::query()
            ->with(['currency.payment', 'currency.code_currency'])
            ->whereHas('currency', function ($q) {
                $q->where('status', '!=', 2)
                    ->where('is_archive', 0);
            })
            ->get();

        $currencies = $perCurrency->map(function (CurrencyAnalytics $item) {
            $currency       = $item->currency;
            $codeCurrency   = $currency?->code_currency;
            $payment        = $currency?->payment;

            $in  = BigDecimal::of((string) ($item->in_amount_usd ?? '0'));
            $out = BigDecimal::of((string) ($item->out_amount_usd ?? '0'));

            return [
                'currency_id'   => (int) ($currency?->id ?? $item->id_currency),
                'code'          => (string) ($codeCurrency?->name ?? ''),
                'payment_name'  => (string) ($payment?->name ?? ''),
                'in_usd'        => (string) $in,
                'out_usd'       => (string) $out,
                'total_usd'     => (string) $in->plus($out),
            ];
        })->values()->all();

        return [
            'mode'       => self::MODE_FIXED,
            'in'         => (string) $in,
            'out'        => (string) $out,
            'total'      => (string) $in->plus($out),
            'currencies' => $currencies,
        ];
    }

    /**
     * Режим "current": считаем по текущему курсу через convert_to_usd().
     * Для каждой активной валюты берём её аналитику и конвертируем в USD на лету.
     */
    protected function getTotalsCurrent(): array
    {
        $currencies = Currency::with(['currency_analytics', 'code_currency'])
            ->enabledCurrency() // или ->active(), в зависимости от твоего scope
            ->get();

        $in  = BigDecimal::zero();
        $out = BigDecimal::zero();

        $perCurrency = [];

        foreach ($currencies as $currency) {
            if (! $currency->currency_analytics || ! $currency->code_currency) {
                continue;
            }

            $analytics = $currency->currency_analytics;
            $code      = $currency->code_currency->name;

            $inUsdForCurrency  = BigDecimal::zero();
            $outUsdForCurrency = BigDecimal::zero();

            if (! empty($analytics->in_amount)) {
                $inUsdValue      = convert_to_usd($code, $analytics->in_amount);
                $inUsdBig        = BigDecimal::of((string) $inUsdValue);
                $in              = $in->plus($inUsdBig);
                $inUsdForCurrency = $inUsdForCurrency->plus($inUsdBig);
            }

            if (! empty($analytics->out_amount)) {
                $outUsdValue     = convert_to_usd($code, $analytics->out_amount);
                $outUsdBig       = BigDecimal::of((string) $outUsdValue);
                $out             = $out->plus($outUsdBig);
                $outUsdForCurrency = $outUsdForCurrency->plus($outUsdBig);
            }

            // Можно выводить только те валюты, у которых есть движение
            if ($inUsdForCurrency->isZero() && $outUsdForCurrency->isZero()) {
                continue;
            }

            $perCurrency[] = [
                'currency_id' => (int) $currency->id,
                'code'        => (string) $code,
                'in_usd'      => (string) $inUsdForCurrency,
                'out_usd'     => (string) $outUsdForCurrency,
                'total_usd'   => (string) $inUsdForCurrency->plus($outUsdForCurrency),
            ];
        }

        return [
            'mode'       => self::MODE_CURRENT,
            'in'         => (string) $in,
            'out'        => (string) $out,
            'total'      => (string) $in->plus($out),
            'currencies' => $perCurrency,
        ];
    }

    public function getTotalsByPeriod(Carbon $from, Carbon $to): array
    {
        $rows = CurrencyAnalyticsDaily::query()
            ->join('currencies', 'currencies_analytics_daily.id_currency', '=', 'currencies.id')
            ->join('code_currency', 'currencies.id_code_currency', '=', 'code_currency.id')
            ->whereBetween('currencies_analytics_daily.date', [$from->toDateString(), $to->toDateString()])
            ->where('currencies.status', '!=', 2)
            ->where('currencies.is_archive', 0)
            ->selectRaw('currencies_analytics_daily.id_currency')
            ->selectRaw('code_currency.name as code')
            ->selectRaw('SUM(currencies_analytics_daily.in_amount_usd)  as in_amount_usd')
            ->selectRaw('SUM(currencies_analytics_daily.out_amount_usd) as out_amount_usd')
            ->groupBy('currencies_analytics_daily.id_currency', 'code_currency.name')
            ->get();

        $in  = BigDecimal::zero();
        $out = BigDecimal::zero();
        $currencies = [];

        foreach ($rows as $row) {
            $inUsd  = BigDecimal::of((string) ($row->in_amount_usd ?? '0'));
            $outUsd = BigDecimal::of((string) ($row->out_amount_usd ?? '0'));

            $in  = $in->plus($inUsd);
            $out = $out->plus($outUsd);

            $currencies[] = [
                'currency_id' => (int) $row->id_currency,
                'code'        => (string) $row->code,
                'in_usd'      => (string) $inUsd,
                'out_usd'     => (string) $outUsd,
                'total_usd'   => (string) $inUsd->plus($outUsd),
            ];
        }

        return [
            'mode'       => self::MODE_FIXED, // для периодов берём fixed (агрегаты)
            'in'         => (string) $in,
            'out'        => (string) $out,
            'total'      => (string) $in->plus($out),
            'currencies' => $currencies,
        ];
    }
}
