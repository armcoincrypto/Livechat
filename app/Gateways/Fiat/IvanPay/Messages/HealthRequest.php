<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\IvanPay\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;
use iEXPackages\Payments\Core\Engine\HealthResponse;

final class HealthRequest extends AbstractRequest
{
    public function getData(): array
    {
        return [];
    }

    protected function sendData(array $data): ResponseInterface
    {
        $started = microtime(true);

        try {
            $raw = $this->sendRequest('get', '/getExchangeRate', []);

            $latency = (int) round((microtime(true) - $started) * 1000);

            return new HealthResponse(
                status: 'ok',
                message: 'Gateway reachable',
                httpStatus: 200,
                latencyMs: $latency,
                data: is_array($raw) ? $raw : [],
                query: $data,
                raw: $raw,
            );

        } catch (\Throwable $e) {
            $latency = (int) round((microtime(true) - $started) * 1000);

            return new HealthResponse(
                status: 'fail',
                message: $e->getMessage(),
                httpStatus: null,
                latencyMs: $latency,
                data: [],
                query: $data,
                raw: null,
            );
        }
    }
}
