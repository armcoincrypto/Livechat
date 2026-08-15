<?php

declare(strict_types=1);

namespace iEXPackages\Courses\Services;

use iEXPackages\Courses\Services\DefaultSources\DefaultParserInterface;
use iEXPackages\Proxy\DTO\ProxyContext;
use iEXPackages\Proxy\Facades\ProxyFacade;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * DefaultParserService
 *
 * Назначение:
 * - Быстро и устойчиво загрузить данные из внешних источников и распарсить.
 *
 * Производительность:
 * - Http::pool() выполняется "окнами" (ограниченная параллельность).
 * - Короткий кеш HTTP raw-body (10–30 сек), чтобы не долбить один и тот же endpoint.
 *
 * Устойчивость:
 * - Ошибка одного источника не ломает остальные.
 * - Circuit breaker с cooldown и экспоненциальной паузой.
 * - Прокси применяется мягко: ошибка прокси => запрос выполняется без прокси.
 *
 * @phpstan-type ParserOptions array{
 *   proxy_id?: int|null,
 *   proxy_url?: string|null,
 *   timeout?: int|null,
 *   connect_timeout?: int|null,
 *   retry?: int|null,
 *   retry_delay_ms?: int|null,
 *   headers?: array<string,string>|null,
 *   cache_ttl?: int|null
 * }
 * @phpstan-type FetchResult array<string, mixed>
 */
final class DefaultParserService
{
    /**
     * Реестр доступных парсеров (alias => parser).
     *
     * @var array<string, DefaultParserInterface>
     */
    private array $parsers;

    /**
     * Circuit breaker.
     */
    private const int FAIL_TTL_HOURS = 2;
    private const int FAIL_THRESHOLD = 5;

    private const int COOLDOWN_MINUTES_BASE = 15;
    private const int COOLDOWN_MINUTES_MAX = 120;

    /**
     * Сетевые дефолты.
     */
    private const int DEFAULT_CONNECT_TIMEOUT = 2;
    private const int DEFAULT_TIMEOUT = 6;
    private const int DEFAULT_RETRY = 1;
    private const int DEFAULT_RETRY_DELAY_MS = 200;

    /**
     * Ограничение параллельности (окно pool).
     */
    private const int POOL_WINDOW_SIZE = 12;

    /**
     * Кеш HTTP (сек). Можно отключить, если поставить 0.
     */
    private const int DEFAULT_HTTP_CACHE_TTL = 15;

    /**
     * @param array<string, class-string<DefaultParserInterface>> $availableSources
     */
    public function __construct(array $availableSources)
    {
        $resolved = [];

        foreach ($availableSources as $alias => $class) {
            $aliasKey = strtolower(trim((string) $alias));
            if ($aliasKey === '') {
                continue;
            }

            /** @var DefaultParserInterface $parser */
            $parser = app($class);
            $resolved[$aliasKey] = $parser;
        }

        $this->parsers = $resolved;
    }

    /**
     * Загрузить данные по списку источников.
     *
     * @param array<int, string> $sourcesToFetch
     * @param array<string, ParserOptions|mixed> $options
     * @return FetchResult
     */
    public function fetch(array $sourcesToFetch, array $options = []): array
    {
        $aliases = $this->normalizeAliases($sourcesToFetch);
        if ($aliases === []) {
            return [];
        }

        $windowSize = (int) ($options['_pool_window_size'] ?? self::POOL_WINDOW_SIZE);
        $windowSize = max(1, min(50, $windowSize));

        $globalCacheTtl = (int) ($options['_cache_ttl'] ?? self::DEFAULT_HTTP_CACHE_TTL);
        $globalCacheTtl = max(0, min(300, $globalCacheTtl)); // cap 5 минут

        $result = [];
        $toFetch = [];

        foreach ($aliases as $aliasRaw) {
            $aliasRaw = trim($aliasRaw);
            if ($aliasRaw === '') {
                continue;
            }

            if ($this->shouldTemporarilySkip($aliasRaw)) {
                $result[$aliasRaw] = ['error' => 'Источник временно пропущен (частые ошибки)'];
                continue;
            }

            $parser = $this->parsers[strtolower($aliasRaw)] ?? null;
            if ($parser === null) {
                $result[$aliasRaw] = ['error' => 'Parser not found'];
                continue;
            }

            $toFetch[] = $aliasRaw;
        }

        if ($toFetch === []) {
            return $result;
        }

        $chunks = array_chunk($toFetch, $windowSize);

        foreach ($chunks as $chunk) {
            /** @var array<string, string> $cachedBodies */
            $cachedBodies = [];

            $needRequest = [];

            // 1) Пробуем кеш для каждого alias из текущего окна
            foreach ($chunk as $aliasRaw) {
                $aliasKey = strtolower($aliasRaw);
                $parser = $this->parsers[$aliasKey];

                /** @var array<string, mixed> $opt */
                $opt = (array) ($options[$aliasRaw] ?? $options[$aliasKey] ?? []);

                $ttl = isset($opt['cache_ttl']) ? (int) $opt['cache_ttl'] : $globalCacheTtl;
                $ttl = max(0, min(300, $ttl));

                if ($ttl <= 0) {
                    $needRequest[] = $aliasRaw;
                    continue;
                }

                $url = (string) $parser->getUrl($opt);
                $query = (array) $parser->getParams($opt);

                $cacheKey = $this->httpCacheKey($aliasRaw, $url, $query, $opt);

                $cached = Cache::get($cacheKey);
                if (is_string($cached) && $cached !== '') {
                    $cachedBodies[$aliasRaw] = $cached;
                    continue;
                }

                $needRequest[] = $aliasRaw;
            }

            // 2) Разбираем кеш-хиты (без HTTP)
            foreach ($cachedBodies as $aliasRaw => $body) {
                $aliasKey = strtolower($aliasRaw);
                $parser = $this->parsers[$aliasKey];

                try {
                    $parsed = $parser->parseResponse($body);

                    if (empty($parsed)) {
                        $payload = ['error' => 'Empty response (cache)'];
                        $result[$aliasRaw] = $payload;
                        $this->registerFailure($aliasRaw, (string) $payload['error']);
                        continue;
                    }

                    $result[$aliasRaw] = $parsed;
                    $this->clearFailures($aliasRaw);
                } catch (Throwable $e) {
                    $payload = ['error' => 'Parsing failed (cache): ' . $e->getMessage()];
                    $result[$aliasRaw] = $payload;
                    $this->registerFailure($aliasRaw, (string) $payload['error']);
                }
            }

            if ($needRequest === []) {
                continue;
            }

            // 3) Делаем HTTP только тем, у кого нет кеша
            /** @var array<string, Response|Throwable|null> $responses */
            $responses = Http::pool(function (Pool $pool) use ($needRequest, $options): array {
                $out = [];

                foreach ($needRequest as $aliasRaw) {
                    $aliasKey = strtolower($aliasRaw);
                    $parser = $this->parsers[$aliasKey];

                    /** @var array<string, mixed> $opt */
                    $opt = (array) ($options[$aliasRaw] ?? $options[$aliasKey] ?? []);

                    $proxyId = (int) ($opt['proxy_id'] ?? 0);

                    $proxyUrl = isset($opt['proxy_url']) ? trim((string) $opt['proxy_url']) : null;
                    if ($proxyUrl === '') {
                        $proxyUrl = null;
                    }

                    $connectTimeout = (int) ($opt['connect_timeout'] ?? self::DEFAULT_CONNECT_TIMEOUT);
                    $timeout = (int) ($opt['timeout'] ?? self::DEFAULT_TIMEOUT);
                    $retry = (int) ($opt['retry'] ?? self::DEFAULT_RETRY);
                    $retryDelay = (int) ($opt['retry_delay_ms'] ?? self::DEFAULT_RETRY_DELAY_MS);

                    $url = (string) $parser->getUrl($opt);
                    $query = (array) $parser->getParams($opt);

                    $headers = [];
                    if (method_exists($parser, 'getHeaders')) {
                        $headers = (array) $parser->getHeaders($opt);
                    }
                    if (isset($opt['headers']) && is_array($opt['headers'])) {
                        $headers = $opt['headers'];
                    }

                    $ctx = ProxyContext::parserGroup($aliasRaw);

                    $req = $pool->as($aliasRaw)
                        ->connectTimeout(max(1, $connectTimeout))
                        ->timeout(max(1, $timeout))
                        ->retry(max(0, $retry), max(0, $retryDelay), throw: false);

                    if ($headers !== []) {
                        $req = $req->withHeaders($headers);
                    }

                    $req = $this->applyProxySoft($req, $proxyId, $proxyUrl, $ctx, $aliasRaw);

                    $out[$aliasRaw] = $req->get($url, $query);
                }

                return $out;
            });

            // 4) Разбор ответов и запись кеша raw body
            foreach ($needRequest as $aliasRaw) {
                $aliasKey = strtolower($aliasRaw);
                $parser = $this->parsers[$aliasKey];

                /** @var array<string, mixed> $opt */
                $opt = (array) ($options[$aliasRaw] ?? $options[$aliasKey] ?? []);
                $proxyId = (int) ($opt['proxy_id'] ?? 0);
                $ctx = ProxyContext::parserGroup($aliasRaw);

                $resp = $responses[$aliasRaw] ?? null;

                if ($resp instanceof Throwable) {
                    $this->markProxyIfNeeded($proxyId, $ctx, false, null, $resp);
                    $payload = ['error' => 'Request failed: ' . $resp->getMessage()];
                    $result[$aliasRaw] = $payload;
                    $this->registerFailure($aliasRaw, (string) $payload['error']);
                    continue;
                }

                if (!$resp instanceof Response) {
                    $payload = ['error' => 'Request failed: unknown response'];
                    $result[$aliasRaw] = $payload;
                    $this->registerFailure($aliasRaw, (string) $payload['error']);
                    continue;
                }

                $this->markProxyIfNeeded($proxyId, $ctx, $resp->successful(), $resp->status(), null);

                if (!$resp->successful()) {
                    $payload = ['error' => "HTTP error {$resp->status()}"];
                    $result[$aliasRaw] = $payload;
                    $this->registerFailure($aliasRaw, (string) $payload['error']);
                    continue;
                }

                $body = (string) $resp->body();

                // записываем кеш raw-body (если ttl > 0)
                $ttl = isset($opt['cache_ttl']) ? (int) $opt['cache_ttl'] : $globalCacheTtl;
                $ttl = max(0, min(300, $ttl));

                if ($ttl > 0 && $body !== '') {
                    $url = (string) $parser->getUrl($opt);
                    $query = (array) $parser->getParams($opt);
                    $cacheKey = $this->httpCacheKey($aliasRaw, $url, $query, $opt);
                    Cache::put($cacheKey, $body, $ttl);
                }

                try {
                    $parsed = $parser->parseResponse($body);

                    if (empty($parsed)) {
                        $payload = ['error' => 'Empty response'];
                        $result[$aliasRaw] = $payload;
                        $this->registerFailure($aliasRaw, (string) $payload['error']);
                        continue;
                    }

                    $result[$aliasRaw] = $parsed;
                    $this->clearFailures($aliasRaw);
                } catch (Throwable $e) {
                    $payload = ['error' => 'Parsing failed: ' . $e->getMessage()];
                    $result[$aliasRaw] = $payload;
                    $this->registerFailure($aliasRaw, (string) $payload['error']);
                }
            }
        }

        return $result;
    }

    /**
     * Ключ кеша HTTP-ответа.
     *
     * @param array<string, mixed> $opt
     */
    private function httpCacheKey(string $aliasRaw, string $url, array $query, array $opt): string
    {
        $headers = [];
        if (isset($opt['headers']) && is_array($opt['headers'])) {
            $headers = $opt['headers'];
        }

        ksort($query);
        ksort($headers);

        return 'iex:courses:default:http:' . sha1(json_encode([
                'alias' => strtolower(trim($aliasRaw)),
                'url' => $url,
                'query' => $query,
                'headers' => $headers,
            ], JSON_UNESCAPED_UNICODE));
    }

    private function applyProxySoft(
        PendingRequest $req,
        int $proxyId,
        ?string $proxyUrl,
        ProxyContext $ctx,
        string $aliasRaw
    ): PendingRequest {
        if ($proxyUrl !== null) {
            return $req->withOptions(['proxy' => $proxyUrl]);
        }

        if ($proxyId <= 0) {
            return $req;
        }

        try {
            return ProxyFacade::applyToHttp($req, $proxyId, $ctx);
        } catch (Throwable $e) {
            Log::warning('Не удалось применить прокси, выполняем запрос без прокси', [
                'alias' => $aliasRaw,
                'proxy_id' => $proxyId,
                'error' => $e->getMessage(),
            ]);

            return $req;
        }
    }

    private function markProxyIfNeeded(
        int $proxyId,
        ProxyContext $ctx,
        bool $success,
        ?int $httpStatus,
        ?Throwable $error
    ): void {
        if ($proxyId <= 0) {
            return;
        }

        try {
            ProxyFacade::markResultById($proxyId, $ctx, $success, $httpStatus, $error, null);
        } catch (Throwable $e) {
            Log::warning('Не удалось отметить результат прокси', [
                'proxy_id' => $proxyId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param array<int, string> $sources
     * @return array<int, string>
     */
    private function normalizeAliases(array $sources): array
    {
        $seen = [];
        $out = [];

        foreach ($sources as $raw) {
            $alias = trim((string) $raw);
            if ($alias === '') {
                continue;
            }

            $k = strtolower($alias);
            if (isset($seen[$k])) {
                continue;
            }

            $seen[$k] = true;
            $out[] = $alias;
        }

        return $out;
    }

    private function shouldTemporarilySkip(string $aliasRaw): bool
    {
        return Cache::has($this->cooldownKey($aliasRaw));
    }

    private function clearFailures(string $aliasRaw): void
    {
        Cache::forget($this->failCountKey($aliasRaw));
        Cache::forget($this->cooldownKey($aliasRaw));
    }

    private function registerFailure(string $aliasRaw, string $reason): void
    {
        $countKey = $this->failCountKey($aliasRaw);

        try {
            $count = (int) Cache::increment($countKey);
            Cache::put($countKey, $count, now()->addHours(self::FAIL_TTL_HOURS));
        } catch (Throwable) {
            $count = (int) Cache::get($countKey, 0);
            $count++;
            Cache::put($countKey, $count, now()->addHours(self::FAIL_TTL_HOURS));
        }

        $paused = false;

        if ($count >= self::FAIL_THRESHOLD) {
            $paused = true;

            $steps = $count - self::FAIL_THRESHOLD;
            $minutes = (int) (self::COOLDOWN_MINUTES_BASE * (2 ** $steps));
            $minutes = min(self::COOLDOWN_MINUTES_MAX, max(self::COOLDOWN_MINUTES_BASE, $minutes));

            Cache::put($this->cooldownKey($aliasRaw), 1, now()->addMinutes($minutes));
        }

        Log::info('Источник обновления курсов вернул ошибку', [
            'alias' => $aliasRaw,
            'fail_count' => $count,
            'paused' => $paused,
            'reason' => $reason,
        ]);
    }

    private function failCountKey(string $aliasRaw): string
    {
        return 'courses:parser:fail:' . strtolower(trim($aliasRaw));
    }

    private function cooldownKey(string $aliasRaw): string
    {
        return 'courses:parser:cooldown:' . strtolower(trim($aliasRaw));
    }
}
