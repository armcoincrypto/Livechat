<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Services;

use iEXPackages\Payments\Core\Contracts\GatewayHttpClientInterface;
use iEXPackages\Payments\Core\Sandbox\Contracts\SandboxManagerInterface;
use iEXPackages\Payments\Logging\GatewayLogger;
use iEXPackages\Payments\Logging\GatewayLogContext;

/**
 * Декоратор над GatewayHttpClientInterface, добавляющий Sandbox/Replay.
 *
 * Что умеет:
 *  - если sandbox включен и операция разрешена:
 *      - пытается найти replay и вернуть его вместо реального HTTP
 *  - если replay не найден:
 *      - выполняет реальный запрос через $inner
 *      - при необходимости записывает replay (если record разрешен)
 *
 * Важно:
 *  - Логи sandbox-hit пишутся здесь (is_sandbox=1)
 *  - Реальный запрос логируется inner-клиентом (is_sandbox=0)
 *  - Запись replay выполняется через SandboxManagerInterface
 */
final class SandboxHttpClientDecorator implements GatewayHttpClientInterface
{
    public function __construct(
        private readonly GatewayHttpClientInterface $inner,
        private readonly SandboxManagerInterface $sandbox,
        private readonly GatewayLogger $logger,
    ) {}

    /**
     * @inheritDoc
     */
    public function send(
        array $spec,
        string $method,
        string $path,
        array $data,
        GatewayLogContext $ctx,
        string $format = 'asJson'
    ): array {
        // Если sandbox/replay не применим — просто делегируем
        if (!$this->sandbox->shouldReplay($ctx)) {
            return $this->inner->send($spec, $method, $path, $data, $ctx, $format);
        }

        $baseUrl = rtrim((string)($spec['baseUrl'] ?? ''), '/');
        if ($baseUrl === '') {
            // пусть inner выбросит корректную ошибку
            return $this->inner->send($spec, $method, $path, $data, $ctx, $format);
        }

        $fullUrl  = $baseUrl . (str_starts_with($path, '/') ? $path : '/' . $path);
        $replayKey = $this->sandbox->makeKey($ctx, $method, $fullUrl, $data);

        // 1) REPLAY HIT
        $replay = $this->sandbox->getReplay($replayKey);
        if (is_array($replay)) {
            // Логируем sandbox hit
            $this->logger->write([
                ...$ctx->toArray(),
                'http_method'     => strtoupper($method),
                'url'             => $fullUrl,
                'response_status' => 200,
                'duration_ms'     => 0,
                'request_headers' => is_array($spec['headers'] ?? null) ? $spec['headers'] : [],
                'request_body'    => $data,
                'response_body'   => $replay,
                'is_sandbox'      => 1,
                'replay_key'      => $replayKey,
            ]);

            return $replay;
        }

        // 2) REPLAY MISS -> real request
        $response = $this->inner->send($spec, $method, $path, $data, $ctx, $format);

        // 3) Запись replay (если разрешено политикой sandbox.record)
        $this->sandbox->storeReplay(
            ctx: $ctx,
            key: $replayKey,
            method: $method,
            url: $fullUrl,
            requestHeaders: is_array($spec['headers'] ?? null) ? $spec['headers'] : [],
            requestBody: $data,
            responseBody: $response,
            httpStatus: 200
        );

        return $response;
    }
}
