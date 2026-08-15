<?php

namespace iEXPackages\Order\Invoices\Concerns;

trait ManagesAttributes
{

    /**
     * Получаем сумму к оплате заявки
     *
     * @return float
     */
    public function getFinallyAmount(): float
    {
        return match ($this->merchantPay->pay_amount) {
            1 => (float)$this->task->give_price_with_comm_pay,
            2 => (float)$this->task->give_price_default,
            default => (float)$this->task->give_price,
        };
    }

    /**
     * Получаем ссылку на мерчант
     *
     * @param array $configProvider
     * @return string
     */
    public function getCallbackUrl(array $configProvider): string
    {
        return (!empty($configProvider['payment_options']['ipn_status_url']))
            ? route('merchant.ipn', [$this->merchantPay->alias, $this->merchantPay->security_hash])
            : route('merchant.receive_money', [$this->merchantPay->alias, $this->merchantPay->security_hash]);
    }
}
