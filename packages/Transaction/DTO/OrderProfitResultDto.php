<?php

namespace iEXPackages\Transaction\DTO;

class OrderProfitResultDto
{
    public function __construct(
        public string $profitAmount,           // прибыль в исходной валюте (currency1)
        public string $profitCurrencyCode,     // код валюты прибыли (например, USDT)
        public string $profitAmountUsd,        // прибыль в USD
        public string $baseCurrencyCode,       // базовая валюта (обычно USD)
        public string $effectivePercent,       // эффективный % от суммы "отдаю"
        public array  $components = [],        // детализация по компонентам
        public array  $ratesSnapshot = [],     // снимок курсов
    ) {}

    public function toArray(): array
    {
        return [
            'profit_amount'            => $this->profitAmount,
            'profit_currency_code'     => $this->profitCurrencyCode,
            'profit_amount_usd'        => $this->profitAmountUsd,
            'base_currency_code'       => $this->baseCurrencyCode,
            'profit_percent_effective' => $this->effectivePercent,
            'components'               => $this->components,
            'rates_snapshot'           => $this->ratesSnapshot,
        ];
    }
}
