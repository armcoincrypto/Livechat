<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Contracts;

use iEXPackages\Payments\Core\Config\GatewayConfig;

interface GatewayInterface
{
    public function getAlias(): string;

    public function initialize(array $parameters = [], bool $merge = false): static;

    public function getParameters(): array;

    public function getParameter(string $key, mixed $default = null): mixed;

    public function setParameter(string $key, mixed $value): static;

    public function addParameters(array $parameters): static;

    public function gatewayConfig(): GatewayConfig;

    /**
     * Базовый, универсальный запуск операции.
     */
    public function run(string $operation, array $input = []): ResponseInterface;

    public function request(string $operation, array $input = []): RequestInterface;

    /**
     * 👇 ДОБАВЛЯЕМ: стандартные методы приёма (Omnipay-стиль)
     */

    /**
     * Инициация платежа (создание счёта / ссылки / инвойса).
     */
//    public function purchase(array $parameters = []): RequestInterface;

//    /**
//     * Обработка callback / IPN / return (completePurchase).
//     */
//    public function completePurchase(array $parameters = []): RequestInterface;
}
