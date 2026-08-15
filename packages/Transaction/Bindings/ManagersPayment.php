<?php

namespace iEXPackages\Transaction\Bindings;

use iEXPackages\Payments\Payments;

trait ManagersPayment
{
    /**
     * Проверяем и зачисляем оплату
     *
     * @return void
     */
    public function checkAndEnroll()
    {
        //
    }

    /**
     * Проверите поступление средств (Для Coin)
     */
    public function checkPayment(): bool
    {
        if (! $this->getMerchant()) {
            return false;
        }

        try {
            $paymentConfig = Payments::forConfig($this->getMerchant()->alias);

            return $paymentConfig->supportsPollingIncoming();
        }catch (\Throwable $e) {
            //
        }

        return false;
    }
}
