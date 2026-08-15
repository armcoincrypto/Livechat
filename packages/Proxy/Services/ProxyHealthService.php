<?php
declare(strict_types=1);

namespace iEXPackages\Proxy\Services;

use iEXPackages\Proxy\Models\Proxy;
use iEXPackages\Proxy\Models\ProxyHealthLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;

/**
 * ProxyHealthService
 *
 * Централизованное здоровье прокси.
 *
 * Правила:
 * - success => fail_count = 0, auto_disabled_until = null, last_checked_at = now()
 * - fail    => fail_count++ (не всегда), last_checked_at = now()
 * - если fail_count >= threshold => включаем cooldown через auto_disabled_until (НЕ трогаем manual status)
 *
 * Разделение состояний:
 * - manual disabled: proxies.status = false (админ выключил)
 * - auto disabled:   proxies.auto_disabled_until > now() (cooldown после ошибок)
 *
 * Важно:
 * - НЕ увеличиваем fail_count на 401/403/429 и другие "логические" ошибки API
 * - Увеличиваем fail_count на ConnectionException и на 5xx
 *
 * Дополнительно:
 * - Пишем историю в proxy_health_logs (аналитика, графики, фильтры)
 */
final class ProxyHealthService
{
    private int $failThreshold;

    /**
     * Сколько секунд держим прокси в cooldown после достижения порога ошибок.
     * Можно вынести в DynamicConfig позже.
     */
    private int $cooldownSeconds;

    public function __construct(?int $failThreshold = null, ?int $cooldownSeconds = null)
    {
        $this->failThreshold = $failThreshold ?? 3;
        $this->cooldownSeconds = $cooldownSeconds ?? 600; // 10 минут
    }

    /**
     * Зафиксировать результат запроса и записать телеметрию.
     *
     * @param Proxy|null       $proxy      Прокси (null если не применялась)
     * @param bool            $success    Успех запроса
     * @param int|null        $httpStatus HTTP статус (если есть)
     * @param \Throwable|null $error      Исключение (если было)
     * @param string          $alias      Контекст: bestchange|parser_group:binance|gateway:...
     * @param int|null        $latencyMs  Время ответа в мс (если измеряли)
     */
    public function markResult(
        ?Proxy $proxy,
        bool $success,
        ?int $httpStatus,
        ?\Throwable $error,
        string $alias,
        ?int $latencyMs = null
    ): void {
        if (!$proxy) {
            return;
        }

        // 1) Обновляем состояние прокси (fail_count/auto_disabled_until/last_checked_at)
        if ($success) {
            $this->markSuccess($proxy);
        } else {
            if (!$this->shouldCountAsFail($httpStatus, $error)) {
                $proxy->last_checked_at = now();
                $proxy->save();
            } else {
                $this->markFail($proxy, $alias);
            }
        }

        // 2) Записываем историю (никогда не ломаем основной поток)
        try {
            ProxyHealthLog::create([
                'proxy_id' => (int) $proxy->id,
                'context' => (string) $alias,
                'success' => (bool) $success,
                'http_status' => $httpStatus !== null ? (int) $httpStatus : null,
                'latency_ms' => $latencyMs !== null ? max(0, (int) $latencyMs) : null,
                'error' => $error ? mb_substr((string) $error->getMessage(), 0, 1024) : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // глушим, чтобы логирование не влияло на работу системы
        }
    }

    private function shouldCountAsFail(?int $status, ?\Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            return true;
        }

        if ($status === null) {
            return true;
        }

        // логические ошибки — не считаем "смертью" прокси
        if (in_array($status, [400, 401, 403, 404, 422, 429], true)) {
            return false;
        }

        // 5xx — считаем fail
        return $status >= 500;
    }

    /**
     * Успех:
     * - fail_count = 0
     * - auto_disabled_until = null (снять cooldown)
     * - last_checked_at = now()
     */
    private function markSuccess(Proxy $proxy): void
    {
        if ((int) $proxy->fail_count !== 0) {
            $proxy->fail_count = 0;
        }

        // успех снимает cooldown
        if ($proxy->auto_disabled_until !== null) {
            $proxy->auto_disabled_until = null;
        }

        $proxy->last_checked_at = now();
        $proxy->save();
    }

    /**
     * Ошибка:
     * - fail_count++
     * - last_checked_at = now()
     * - если достигли порога — включаем cooldown через auto_disabled_until
     *
     * Важно: manual status (status) НЕ трогаем.
     */
    private function markFail(Proxy $proxy, string $alias): void
    {
        try {
            Proxy::whereKey($proxy->id)->increment('fail_count');
            $proxy->refresh();
        } catch (\Throwable) {
            $proxy->fail_count++;
        }

        $proxy->last_checked_at = now();

        if ((int) $proxy->fail_count >= $this->failThreshold) {
            // cooldown (авто-отключение)
            $proxy->auto_disabled_until = now()->addSeconds($this->cooldownSeconds);

            Log::warning(sprintf(
                'Auto-cooldown proxy: id=%d fail_count=%d until=%s alias=%s',
                (int) $proxy->id,
                (int) $proxy->fail_count,
                $proxy->auto_disabled_until?->toIso8601String(),
                $alias
            ));
        }

        $proxy->save();
    }
}
