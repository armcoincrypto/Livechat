<?php
declare(strict_types=1);

namespace iEXPackages\Proxy;

use iEXPackages\Proxy\DTO\ProxyContext;
use iEXPackages\Proxy\Models\Proxy;
use iEXPackages\Proxy\Services\ProxyHealthService;
use iEXPackages\Proxy\Services\ProxyHttpApplier;
use iEXPackages\Proxy\Services\ProxyRepository;
use iEXPackages\Proxy\Services\ProxyUrlBuilder;
use Illuminate\Http\Client\PendingRequest;

/**
 * ProxyManager
 *
 * Главный сервис пакета.
 * Именно его должны использовать все модули системы.
 *
 * Он даёт 4 главные операции:
 *  1) resolveById()  — получить Proxy модель по proxy_id
 *  2) buildUrl()     — собрать proxy URL
 *  3) applyToHttp()  — применить прокси к Laravel Http PendingRequest
 *  4) markResult()   — обновить здоровье прокси по результату запроса
 *
 * Почему так:
 * - Модули не знают о ProxyRepository/UrlBuilder/Health
 * - У тебя всегда одна точка для изменений поведения
 */
final class ProxyManager
{
    public function __construct(
        private readonly ProxyRepository $repository,
        private readonly ProxyUrlBuilder $urlBuilder,
        private readonly ProxyHttpApplier $httpApplier,
        private readonly ProxyHealthService $health,
    ) {}

    /**
     * Получить Proxy по ID (может вернуть null).
     */
    public function resolveById(int $proxyId): ?Proxy
    {
        return $this->repository->findById($proxyId);
    }

    /**
     * Получить proxy URL по proxy_id (или null, если прокси неактивна/невалидна).
     */
    public function urlById(int $proxyId): ?string
    {
        $proxy = $this->resolveById($proxyId);
        return $this->buildUrl($proxy);
    }

    /**
     * Собрать proxy URL из Proxy модели (или null, если использовать нельзя).
     */
    public function buildUrl(?Proxy $proxy): ?string
    {
        if (!$this->repository->isUsable($proxy)) {
            return null;
        }

        /** @var Proxy $proxy */
        return $this->urlBuilder->build($proxy);
    }

    /**
     * Применить прокси к PendingRequest по proxy_id.
     *
     * Использование:
     *   $http = ProxyFacade::applyToHttp(Http::baseUrl(...), $proxyId, ProxyContext::bestChange());
     */
    public function applyToHttp(PendingRequest $http, int $proxyId, ProxyContext $context): PendingRequest
    {
        $proxy = $this->resolveById($proxyId);
        $proxyUrl = $this->buildUrl($proxy);

        // apply
        $http = $this->httpApplier->apply($http, $proxyUrl);

        return $http;
    }

    /**
     * Сообщить ProxyHealthService результат запроса.
     *
     * Важно: этот метод должен вызываться ПОСЛЕ запроса, когда есть response->status()
     * или exception.
     */
    public function markResultById(
        int $proxyId,
        ProxyContext $context,
        bool $success,
        ?int $httpStatus,
        ?\Throwable $error,
        ?int $latencyMs = null
    ): void {
        $proxy = $this->resolveById($proxyId);

        $this->health->markResult(
            proxy: $proxy,
            success: $success,
            httpStatus: $httpStatus,
            error: $error,
            alias: $context->alias(),
            latencyMs: $latencyMs,
        );
    }

    /**
     * Вариант, когда Proxy уже известен (если ты кешировал его выше).
     */
    public function markResult(
        ?Proxy $proxy,
        ProxyContext $context,
        bool $success,
        ?int $httpStatus,
        ?\Throwable $error,
        ?int $latencyMs = null
    ): void {
        $this->health->markResult(
            proxy: $proxy,
            success: $success,
            httpStatus: $httpStatus,
            error: $error,
            alias: $context->alias(),
            latencyMs: $latencyMs,
        );
    }
}
