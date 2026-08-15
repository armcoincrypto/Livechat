<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Sandbox;

use iEXPackages\Payments\Core\Models\GatewayReplay;

final class ReplayStore
{
    /**
     * @param int $ttlSeconds 0 = без TTL
     */
    public function find(string $key, int $ttlSeconds = 0): ?array
    {
        $q = GatewayReplay::query()->where('replay_key', $key);

        if ($ttlSeconds > 0) {
            $q->where('updated_at', '>=', now()->subSeconds($ttlSeconds));
        }

        /** @var GatewayReplay|null $row */
        $row = $q->first();

        if (!$row) {
            return null;
        }

        return is_array($row->response_json) ? $row->response_json : null;
    }

    public function upsert(
        string $key,
        string $gateway,
        string $operation,
        string $direction,
        string $httpMethod,
        string $url,
        array $requestHeaders,
        array $requestBody,
        array $responseBody,
        int $httpStatus
    ): void {
        GatewayReplay::query()->updateOrCreate(
            ['replay_key' => $key],
            [
                'gateway'         => $gateway,
                'operation'       => $operation,
                'direction'       => $direction,
                'http_method'     => $httpMethod,
                'url'             => $url,
                'request_headers' => $requestHeaders,
                'request_body'    => $requestBody,
                'response_json'   => $responseBody,
                'http_status'     => $httpStatus,
            ]
        );
    }
}
