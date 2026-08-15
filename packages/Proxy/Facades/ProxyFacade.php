<?php
declare(strict_types=1);

namespace iEXPackages\Proxy\Facades;

use Illuminate\Support\Facades\Facade;
use iEXPackages\Proxy\ProxyManager;

/**
 * ProxyFacade
 *
 * Удобный доступ к ProxyManager из любого места проекта.
 *
 * @method static \iEXPackages\Proxy\Models\Proxy|null resolveById(int $proxyId)
 * @method static string|null urlById(int $proxyId)
 * @method static \Illuminate\Http\Client\PendingRequest applyToHttp(\Illuminate\Http\Client\PendingRequest $http, int $proxyId, \iEXPackages\Proxy\DTO\ProxyContext $context)
 * @method static void markResultById(int $proxyId, \iEXPackages\Proxy\DTO\ProxyContext $context, bool $success, ?int $httpStatus, ?\Throwable $error)
 * @method static void markResult(?\iEXPackages\Proxy\Models\Proxy $proxy, \iEXPackages\Proxy\DTO\ProxyContext $context, bool $success, ?int $httpStatus, ?\Throwable $error)
 */
final class ProxyFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ProxyManager::class;
    }
}
