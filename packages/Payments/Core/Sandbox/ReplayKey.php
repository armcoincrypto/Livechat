<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Sandbox;

use iEXPackages\Payments\Logging\GatewayLogContext;

final class ReplayKey
{
    private function __construct() {}

    /**
     * Стабильный ключ для replay.
     * Меняется при изменении:
     * - alias
     * - operation
     * - direction
     * - http_method
     * - url_path
     * - request_body
     */
    public static function make(GatewayLogContext $ctx, string $method, string $url, array $requestBody): string
    {
        $method = strtoupper(trim($method));

        $path = (string) (parse_url($url, PHP_URL_PATH) ?? $url);
        $path = $path === '' ? '/' : $path;

        // стабилизируем body
        $body = self::stableSortRecursive($requestBody);

        $payload = [
            'gateway'    => (string)($ctx->gateway ?? ''),
            'operation'  => (string)($ctx->operation ?? ''),
            'direction'  => (string)($ctx->direction ?? ''),
            'method'     => $method,
            'path'       => $path,
            'body'       => $body,
        ];

        return sha1(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    private static function stableSortRecursive(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = self::stableSortRecursive($v);
            }
        }

        if (!array_is_list($data)) {
            ksort($data);
        }

        return $data;
    }
}
