<?php
/**
 * Created by PhpStorm.
 * User: steei
 * Date: 02.06.2019
 * Time: 22:01
 */

namespace iEXPackages\Transaction\Contracts;

interface PaymentContract
{
    /**
     * Обработчик автовыплаты
     *
     * @return void
     */
    public function handle();
}
