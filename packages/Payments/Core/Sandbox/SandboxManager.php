<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Sandbox;

use iEXPackages\Payments\Core\Sandbox\Contracts\SandboxManagerInterface;
use iEXPackages\Payments\Logging\GatewayLogContext;

final class SandboxManager implements SandboxManagerInterface
{
    public function __construct(
        private readonly ReplayStore $store,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('payments.sandbox.enabled', false);
    }

    public function makeKey(GatewayLogContext $ctx, string $method, string $url, array $requestBody): string
    {
        return ReplayKey::make($ctx, $method, $url, $requestBody);
    }

    /**
     * Разрешена ли операция sandbox/replay.
     */
    private function operationAllowed(GatewayLogContext $ctx): bool
    {
        $allowed = (array) config('payments.sandbox.operations', []);
        if ($allowed === []) {
            return true; // пусто = разрешить все
        }

        return in_array((string) $ctx->operation, $allowed, true);
    }

    /**
     * Нужно ли пытаться replay.
     *
     * Логика:
     * - sandbox.enabled=true => replay разрешён
     * - sandbox.enabled=false => replay запрещён (если allow_replay_without_sandbox=false)
     * - операции фильтруются payments.sandbox.operations
     */
    public function shouldReplay(GatewayLogContext $ctx): bool
    {
        if (!$this->operationAllowed($ctx)) {
            return false;
        }

        $allowReplayWithoutSandbox = (bool) config('payments.sandbox.replay.allow_replay_without_sandbox', false);

        if ($this->isEnabled()) {
            return true;
        }

        return $allowReplayWithoutSandbox;
    }

    /**
     * Получить replay (с учетом TTL).
     */
    public function getReplay(string $key): ?array
    {
        $ttlSeconds = (int) config('payments.sandbox.replay.ttl_seconds', 3600);
        $ttlSeconds = max(0, $ttlSeconds);

        return $this->store->find($key, $ttlSeconds);
    }

    /**
     * Сохранять ли replay.
     *
     * Политика:
     * - record=true И sandbox.enabled=true
     * - операции должны проходить фильтр
     */
    private function canRecord(GatewayLogContext $ctx): bool
    {
        if (!$this->operationAllowed($ctx)) {
            return false;
        }

        if (!$this->isEnabled()) {
            return false;
        }

        return (bool) config('payments.sandbox.record', false);
    }

    public function storeReplay(
        GatewayLogContext $ctx,
        string $key,
        string $method,
        string $url,
        array $requestHeaders,
        array $requestBody,
        array $responseBody,
        int $httpStatus
    ): void {
        if (!$this->canRecord($ctx)) {
            return;
        }

        $this->store->upsert(
            key: $key,
            gateway: (string)($ctx->gateway ?? ''),
            operation: (string)($ctx->operation ?? ''),
            direction: (string)($ctx->direction ?? ''),
            httpMethod: strtoupper(trim($method)),
            url: $url,
            requestHeaders: $requestHeaders,
            requestBody: $requestBody,
            responseBody: $responseBody,
            httpStatus: $httpStatus
        );
    }
}
