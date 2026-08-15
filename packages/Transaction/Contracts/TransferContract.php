<?php

namespace iEXPackages\Transaction\Contracts;

interface TransferContract
{
    /**
     * Вызов обработчика выплаты средств
     *
     * @return void
     */
    public function call();
}
