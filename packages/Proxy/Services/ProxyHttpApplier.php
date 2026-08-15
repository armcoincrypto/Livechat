<?php
declare(strict_types=1);

namespace iEXPackages\Proxy\Services;

use Illuminate\Http\Client\PendingRequest;

/**
 * ProxyHttpApplier
 *
 * Единственный класс, который "знает", как именно Laravel Http подключает прокси.
 *
 * Плюсы:
 * - все модули вызывают один метод
 * - если позже захочешь поддержку Guzzle/cURL — добавишь другие Applier-классы,
 *   а ProxyManager будет решать какой применить.
 */
final class ProxyHttpApplier
{
    /**
     * Применить proxy URL к PendingRequest.
     *
     * @param PendingRequest $http     Laravel Http PendingRequest
     * @param string|null    $proxyUrl Пример: "http://user:pass@1.2.3.4:8080"
     */
    public function apply(PendingRequest $http, ?string $proxyUrl): PendingRequest
    {
        if ($proxyUrl === null || $proxyUrl === '') {
            return $http;
        }

        return $http->withOptions([
            'proxy' => $proxyUrl,
        ]);
    }
}
