<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Sandbox\Contracts;

use iEXPackages\Payments\Logging\GatewayLogContext;

interface SandboxManagerInterface
{
    public function isEnabled(): bool;

    /**
     * Строит replayKey по контексту и запросу.
     *
     * @param GatewayLogContext $ctx
     * @param string $method
     * @param string $url
     * @param array $requestBody
     * @return string
     */
    public function makeKey(GatewayLogContext $ctx, string $method, string $url, array $requestBody): string;

    /**
     * Если true — вместо реального HTTP возвращаем сохранённый replay.
     */
    public function shouldReplay(GatewayLogContext $ctx): bool;

    /**
     * Получить replay по ключу.
     *
     * @return array|null
     */
    public function getReplay(string $key): ?array;

    /**
     * Сохранить replay (request/response).
     */
    public function storeReplay(
        GatewayLogContext $ctx,
        string $key,
        string $method,
        string $url,
        array $requestHeaders,
        array $requestBody,
        array $responseBody,
        int $httpStatus
    ): void;
}
