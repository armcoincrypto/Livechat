<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Kobbopay\Messages;

use iEXPackages\Payments\Core\Contracts\BlockchainPaymentResponseInterface;
use iEXPackages\Payments\Core\Contracts\FetchPaymentResponseInterface;
use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class FetchPaymentResponse extends AbstractResponse implements FetchPaymentResponseInterface, BlockchainPaymentResponseInterface
{
    private const STATUS_SUCCESS = 'SUCCESS';
    private const STATUS_PENDING = 'ACCEPTED';
    private const STATUS_ERROR   = 'ERROR';

    public function canRegisterTransaction(): bool
    {
        return $this->getTransactionHash() !== null && $this->getTransactionHash() !== '';
    }

    public function exists(): bool
    {
        $tx = $this->dataGet('transaction');

        return is_array($tx) && $tx !== [];
    }

    public function getStatus(): ?string
    {
        $status = $this->safeString($this->dataGet('transaction.status'));

        return $status !== null ? strtoupper($status) : null;
    }

    public function isSuccessful(): bool
    {
        return $this->exists() && $this->getStatus() === self::STATUS_SUCCESS;
    }

    public function isPending(): bool
    {
        return $this->exists() && $this->getStatus() === self::STATUS_PENDING;
    }

    public function isCancelled(): bool
    {
        return $this->exists() && $this->getStatus() === self::STATUS_ERROR;
    }

    public function getAmount(): ?string
    {
        return $this->safeDecimal($this->dataGet('transaction.amount'));
    }

    public function getCurrency(): ?string
    {
        $token = $this->safeString($this->dataGet('transaction.token'));

        if ($token === null) {
            return null;
        }

        return $this->normalizeCurrencyToken($token);
    }

    private function normalizeCurrencyToken(string $token): string
    {
        return match (strtoupper($token)) {
            'USDTBSC', 'USDTERC', 'USDTTRC' => 'USDT',
            default => $token,
        };
    }

    public function getTransactionHash(): ?string
    {
        $hash = $this->safeString($this->dataGet('transaction.hash'));

        return $hash !== '' ? $hash : null;
    }

    public function getExternalId(): ?string
    {
        return $this->safeString($this->queryGet('tracker_id'));
    }

    public function getErrorMessage(): ?string
    {
        if (!$this->exists()) {
            return 'Транзакция не найдена';
        }

        if ($this->getTransactionHash() === null) {
            if ($this->isPending()) {
                return null;
            }

            return 'Средства не поступили';
        }

        if ($this->isCancelled()) {
            return $this->safeString($this->dataGet('message'))
                ?? $this->safeString($this->dataGet('error'))
                ?? 'Заявка отменена';
        }

        return null;
    }

    public function getStatusDescription(): string
    {
        if (!$this->exists()) {
            return 'Транзакция не найдена';
        }

        if ($this->getTransactionHash() === null && !$this->isPending()) {
            return 'Средства не поступили';
        }

        return match ($this->getStatus()) {
            self::STATUS_PENDING => 'Транзакция в процессе обработки...',
            self::STATUS_ERROR   => 'Заявка отменена',
            self::STATUS_SUCCESS => 'Транзакция успешно выполнена',
            default              => 'Статус не определен',
        };
    }
}
