<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Callback\DTO;

/**
 * Результат обработки callback.
 *
 * Мы всегда стараемся отвечать провайдеру "OK" (200),
 * чтобы он не долбил повторными попытками, кроме явных
 * нарушений безопасности (неверный hash / запрещённый IP).
 */
final class CallbackHttpResult
{
    public function __construct(
        public readonly int $httpStatus,
        public readonly string $body,
    ) {}
}
