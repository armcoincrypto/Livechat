<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Http;

use App\Settings\BestChangeConfig;
use iEXPackages\BestChange\Contracts\BestChangeHttpClientInterface;
use iEXPackages\Proxy\DTO\ProxyContext;
use iEXPackages\Proxy\Facades\ProxyFacade;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * BestChangeHttpClient
 *
 * Низкоуровневый HTTP-клиент BestChange API v2.
 *
 * Назначение:
 * - Выполнять HTTP-запросы к BestChange API и возвращать "сырые" данные API (array).
 * - Обеспечивать отказоустойчивость через зеркала и circuit breaker.
 * - Соблюдать rate limit, рекомендованный BestChange.
 * - Работать через прокси (proxy_id берётся из BestChangeConfig) и обновлять состояние прокси через Proxy-модуль.
 *
 * Важно:
 * - Класс не форматирует данные "под проект" и не кэширует справочники.
 * - Кэширование/нормализация выполняются выше: BestChangeCatalogRepository / RatesConnection.
 */
final class BestChangeHttpClient implements BestChangeHttpClientInterface
{
    /**
     * Префикс API BestChange v2.
     * Итоговый путь запроса: /v2/{apiKey}{endpoint}
     */
    private const API_PREFIX = '/v2/';

    private readonly RequestRateLimiter $rateLimiter;
    private readonly HostCircuitBreaker $hostCircuitBreaker;

    public function __construct(
        private readonly BestChangeConfig $settings,
    ) {
        /**
         * Инфраструктура создаётся внутри клиента.
         * Это выполнено осознанно (без DB в ServiceProvider).
         */
        $this->hostCircuitBreaker = new HostCircuitBreaker();

        $this->rateLimiter = new RequestRateLimiter(
            bucketKey: 'bestchange:rps',
            limitRps:  $this->rpsLimit(),
            sleepUs:   $this->rpsSleepUs(),
        );
    }

    public function fetchLangs(): array
    {
        $payload = $this->requestJson('/langs');
        return (array)($payload['langs'] ?? []);
    }

    public function fetchGroups(string $lang): array
    {
        $payload = $this->requestJson("/groups/{$lang}");
        return (array)($payload['groups'] ?? []);
    }

    public function fetchCountries(string $lang): array
    {
        $payload = $this->requestJson("/countries/{$lang}");
        return (array)($payload['countries'] ?? []);
    }

    public function fetchCities(string $lang): array
    {
        $payload = $this->requestJson("/cities/{$lang}");
        return (array)($payload['cities'] ?? []);
    }

    public function fetchCurrencies(string $lang): array
    {
        $payload = $this->requestJson("/currencies/{$lang}");
        return (array)($payload['currencies'] ?? []);
    }

    public function fetchChangers(string $lang): array
    {
        $payload = $this->requestJson("/changers/{$lang}");
        return (array)($payload['changers'] ?? []);
    }

    public function fetchRatesBatch(array $pairKeys): array
    {
        $pairKeys = $this->normalizePairKeys($pairKeys);
        if ($pairKeys === []) {
            return [];
        }

        $result = [];

        foreach (array_chunk($pairKeys, 500) as $chunk) {
            $pairList = implode('+', $chunk);

            $payload = $this->requestJson("/rates/{$pairList}");
            $rates = $payload['rates'] ?? null;

            if (!is_array($rates)) {
                continue;
            }

            foreach ($rates as $key => $rows) {
                $result[(string) $key] = is_array($rows) ? $rows : [];
            }
        }

        return $result;
    }

    public function fetchPresence(string $pairKey): ?array
    {
        $pairKey = trim($pairKey);
        if ($pairKey === '') {
            return null;
        }

        $payload = $this->requestJson("/presences/{$pairKey}");
        $list = $payload['presences'] ?? null;

        if (!is_array($list) || $list === []) {
            return null;
        }

        $first = $list[0] ?? null;
        return is_array($first) ? $first : null;
    }

    /**
     * Выполнить запрос к BestChange API v2 с учетом:
     * - зеркал
     * - SSRF/allowed_hosts
     * - circuit breaker
     * - rate limiter
     * - прокси (через ProxyFacade) и здоровья прокси (ProxyFacade::markResultById)
     *
     * @param string $path Например: "/cities/ru"
     * @return array<string,mixed>
     *
     * @throws ConnectionException
     * @throws \RuntimeException
     */
    private function requestJson(string $path): array
    {
        $apiKey         = $this->apiKey();
        $timeoutSeconds = $this->timeoutSeconds();
        $connectTimeout = $this->connectTimeoutSeconds();
        $retryCount     = $this->retryCount();
        $retryDelayMs   = $this->retryDelayMs();

        $baseUrls     = $this->apiUrls();
        $allowedHosts = $this->allowedHosts();

        // proxy_id берём из BestChangeConfig (DynamicConfig)
        $proxyId = (int) $this->settings->proxyId();
        $ctx     = ProxyContext::bestChange();

        // Итоговый URI запроса к конкретному зеркалу
        $uri = self::API_PREFIX . $apiKey . $path;

        foreach ($baseUrls as $baseUrl) {
            $host = $this->extractHost($baseUrl);
            if ($host === null) {
                continue;
            }

            if (!$this->isHostAllowed($host, $allowedHosts)) {
                continue;
            }

            if ($this->hostCircuitBreaker->isBlocked($host)) {
                continue;
            }

            $this->rateLimiter->throttle();

            // latency считаем для каждого запроса к зеркалу
            $startedAt = microtime(true);

            try {
                $response = $this->sendGet(
                    baseUrl: $baseUrl,
                    host: $host,
                    connectTimeoutSeconds: $connectTimeout,
                    timeoutSeconds: $timeoutSeconds,
                    retryCount: $retryCount,
                    retryDelayMs: $retryDelayMs,
                    uri: $uri,
                    proxyId: $proxyId,
                    ctx: $ctx,
                );

                $latencyMs = (int) ((microtime(true) - $startedAt) * 1000);

                if ($response->successful()) {
                    $this->hostCircuitBreaker->markSuccess($host);

                    if ($proxyId > 0) {
                        ProxyFacade::markResultById($proxyId, $ctx, true, $response->status(), null, $latencyMs);
                    }

                    return (array) $response->json();
                }

                $status = $response->status();

                if (in_array($status, [401, 403], true)) {
                    if ($proxyId > 0) {
                        ProxyFacade::markResultById($proxyId, $ctx, false, $status, null, $latencyMs);
                    }
                    throw new \RuntimeException("BestChange API: доступ запрещен (HTTP {$status}). Проверь API ключ.");
                }

                if ($status === 429) {
                    if ($proxyId > 0) {
                        ProxyFacade::markResultById($proxyId, $ctx, false, $status, null, $latencyMs);
                    }
                    continue;
                }

                if ($status >= 500) {
                    $this->hostCircuitBreaker->markFail($host);
                }

                if ($proxyId > 0) {
                    ProxyFacade::markResultById($proxyId, $ctx, false, $status, null, $latencyMs);
                }

            } catch (ConnectionException $e) {
                $this->hostCircuitBreaker->markFail($host);

                $latencyMs = (int) ((microtime(true) - $startedAt) * 1000);

                if ($proxyId > 0) {
                    ProxyFacade::markResultById($proxyId, $ctx, false, null, $e, $latencyMs);
                }
            }
        }

        throw new ConnectionException('BestChange API: все зеркала недоступны');
    }

    /**
     * Отправить GET-запрос с правильными заголовками и настройками.
     *
     * Важно:
     * - Применение прокси выполняется централизованно через ProxyFacade.
     */
    private function sendGet(
        string $baseUrl,
        string $host,
        int $connectTimeoutSeconds,
        int $timeoutSeconds,
        int $retryCount,
        int $retryDelayMs,
        string $uri,
        int $proxyId,
        ProxyContext $ctx,
    ): Response {
        $http = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->withHeaders($this->defaultHeaders($host))
            ->connectTimeout($connectTimeoutSeconds)
            ->timeout($timeoutSeconds)
            ->retry($retryCount, $retryDelayMs);

        // применяем прокси (если proxyId задан и прокси активна)
        if ($proxyId > 0) {
            $http = ProxyFacade::applyToHttp($http, $proxyId, $ctx);
        }

        return $http->get($uri);
    }

    /**
     * @return array<string,string>
     */
    private function defaultHeaders(string $host): array
    {
        return [
            'Host' => $host,
            'Accept-Encoding' => 'gzip',
            'Connection' => 'keep-alive',
        ];
    }

    private function apiKey(): string
    {
        return remove_all_spaces($this->settings->apiKey());
    }

    private function timeoutSeconds(): int
    {
        return max(1, (int) $this->settings->timeout());
    }

    private function connectTimeoutSeconds(): int
    {
        return max(1, (int) config('courses.bestchange.http.connect_timeout', 5));
    }

    private function retryCount(): int
    {
        return max(0, (int) config('courses.bestchange.http.retries', 1));
    }

    private function retryDelayMs(): int
    {
        return max(0, (int) config('courses.bestchange.http.retry_delay_ms', 200));
    }

    private function rpsLimit(): int
    {
        return max(1, (int) config('courses.bestchange.http.rps', 30));
    }

    private function rpsSleepUs(): int
    {
        return max(1, (int) config('courses.bestchange.http.rps_sleep_us', 50_000));
    }

    /**
     * @return string[]
     */
    private function apiUrls(): array
    {
        $urls = (array) config('courses.bestchange.api_urls', []);
        $out = [];

        foreach ($urls as $u) {
            $u = trim((string) $u);
            if ($u !== '') {
                $out[] = $u;
            }
        }

        return $out;
    }

    /**
     * @return string[]
     */
    private function allowedHosts(): array
    {
        $hosts = (array) (config('courses.bestchange.allowed_hosts', []) ?: []);
        $out = [];

        foreach ($hosts as $h) {
            $h = strtolower(trim((string) $h));
            if ($h !== '') {
                $out[] = $h;
            }
        }

        return $out;
    }

    private function extractHost(string $baseUrl): ?string
    {
        $host = (string) parse_url($baseUrl, PHP_URL_HOST);
        return $host !== '' ? strtolower(trim($host)) : null;
    }

    /**
     * SSRF защита:
     * - запрет localhost
     * - запрет IP
     * - optional whitelist allowed_hosts
     */
    private function isHostAllowed(string $host, array $allowedHosts): bool
    {
        $host = strtolower(trim($host));

        if ($host === '' || $host === 'localhost') {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        return $allowedHosts === [] ? true : in_array($host, $allowedHosts, true);
    }

    /**
     * @param string[] $pairKeys
     * @return string[]
     */
    private function normalizePairKeys(array $pairKeys): array
    {
        $clean = [];
        foreach ($pairKeys as $k) {
            $k = trim((string) $k);
            if ($k !== '') {
                $clean[] = $k;
            }
        }

        return array_values(array_unique($clean));
    }
}
