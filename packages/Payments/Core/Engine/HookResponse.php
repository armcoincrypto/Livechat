<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Engine;

use iEXPackages\Payments\Core\Engine\AbstractResponse;

/**
 * Универсальный ответ для hook-операций.
 *
 * Ожидаемый формат data (любой шлюз может вернуть):
 * [
 *   'ok' => bool,                 // true = можно продолжать
 *   'pending' => bool,            // true = ещё рано завершать
 *   'cancelled' => bool,          // true = блокируем/ошибка
 *   'message' => string|null,     // человекочитаемо
 *   'meta' => array               // любые данные
 * ]
 */
final class HookResponse extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        return (bool) ($this->dataGet('ok') ?? false);
    }

    public function isPending(): bool
    {
        return (bool) ($this->dataGet('pending') ?? false);
    }

    public function isCancelled(): bool
    {
        return (bool) ($this->dataGet('cancelled') ?? false);
    }

    public function getErrorMessage(): ?string
    {
        if ($this->isSuccessful() || $this->isPending()) {
            return null;
        }

        $msg = $this->safeString($this->dataGet('message'));
        return $msg !== '' ? $msg : 'Операция отклонена hook-правилом';
    }

    public function getMessage(): ?string
    {
        $msg = $this->safeString($this->dataGet('message'));
        return $msg !== '' ? $msg : null;
    }

    public function getMeta(): array
    {
        $m = $this->dataGet('meta');
        return is_array($m) ? $m : [];
    }
}
