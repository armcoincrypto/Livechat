<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Contracts;

use iEXPackages\Payments\Logging\GatewayLogContext;

interface GatewayHttpClientInterface
{
    /**
     * Универсальный вызов HTTP с логированием.
     *
     * @param array $spec   Настройки соединения/подписи/headers.
     * @param array $data   Payload запроса.
     */
    public function send(
        array $spec,
        string $method,
        string $path,
        array $data,
        GatewayLogContext $ctx,
        string $format = 'asJson'
    ): array;
}
