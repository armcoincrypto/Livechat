<?php

namespace iEXPackages\Payments\Core\Contracts;

/**
 * Для crypto-проверок, где есть хэш транзакции и стадия регистрации.
 */
interface BlockchainPaymentResponseInterface
{
    public function getTransactionHash(): ?string;

    /**
     * Нужно ли выставлять REGISTERING_TRANSACTION (register_tx=1),
     * если tx_hash уже появился.
     */
    public function canRegisterTransaction(): bool;
}
