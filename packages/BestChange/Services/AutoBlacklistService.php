<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Services;

use App\Models\BestChangeExchangerCooldown;
use App\Settings\BestChangeConfig;
use Illuminate\Support\Facades\Cache;

/**
 * AutoBlacklistService
 *
 * Управляет временной блокировкой обменников (cooldown).
 *
 * Назначение:
 * - временно исключать обменники из расчёта, если они системно ведут себя плохо
 * - хранить блокировку в БД (прозрачность для админки и сохранение при рестартах)
 * - держать быстрый кэш активных блокировок, чтобы не делать SELECT на каждую строку rates
 *
 * Принцип:
 * - isBlocked() должен быть быстрым (Cache → при промахе обновление из БД)
 * - block() не должен сокращать существующую блокировку (только продлевать)
 */
final class AutoBlacklistService
{
    private const CACHE_KEY = 'bestchange:auto_blacklist:active';
    private const CACHE_TTL_SECONDS = 60;

    /** @var array<int,int>|null */
    private ?array $localMap = null;
    private int $localLoadedAt = 0;

    public function __construct(private readonly BestChangeConfig $settings) {}

    public function isBlocked(int $changerId): bool
    {
        if ($changerId <= 0 || !$this->isFeatureEnabled()) {
            return false;
        }

        $map = $this->activeMapFast();
        $until = $map[$changerId] ?? 0;

        if ($until <= 0) return false;

        if ($until > time()) return true;

        // истекло — лениво чистим
        $this->unblock($changerId);
        return false;
    }

    public function remainingSeconds(int $changerId): int
    {
        if ($changerId <= 0 || !$this->isFeatureEnabled()) {
            return 0;
        }

        $map = $this->activeMapFast();
        $until = $map[$changerId] ?? 0;

        return $until > 0 ? max(0, $until - time()) : 0;
    }

    public function block(int $changerId, int $minutes, string $reason, array $meta = []): void
    {
        if ($changerId <= 0 || !$this->isFeatureEnabled()) return;

        $minutes = $minutes > 0 ? $minutes : $this->defaultMinutes();
        if ($minutes <= 0) return;

        $reason = trim($reason) !== '' ? trim($reason) : 'auto_blacklist';

        $newUntil = now()->addMinutes($minutes);

        $row = BestChangeExchangerCooldown::query()
            ->where('changer_id', $changerId)
            ->first(['changer_id','blocked_until']);

        if ($row && $row->blocked_until && $row->blocked_until->isFuture() && $row->blocked_until->greaterThan($newUntil)) {
            $this->touchCacheLocal($changerId, $row->blocked_until->getTimestamp());
            return;
        }

        BestChangeExchangerCooldown::query()->updateOrCreate(
            ['changer_id' => $changerId],
            [
                'blocked_until' => $newUntil,
                'reason' => $reason,
                'minutes' => $minutes,
                'meta' => $this->normalizeMeta($meta),
            ]
        );

        $this->touchCacheLocal($changerId, $newUntil->getTimestamp());
    }

    public function unblock(int $changerId): void
    {
        if ($changerId <= 0) return;

        BestChangeExchangerCooldown::query()->where('changer_id', $changerId)->delete();

        $map = $this->activeMapFast();
        unset($map[$changerId]);

        // обновляем и локально, и внешний cache
        $this->localMap = $map;
        $this->localLoadedAt = time();
        Cache::put(self::CACHE_KEY, $map, self::CACHE_TTL_SECONDS);
    }

    // ---------------------------------------------------------------------

    private function isFeatureEnabled(): bool
    {
        return method_exists($this->settings, 'autoBlacklistEnabled')
            ? (bool)$this->settings->autoBlacklistEnabled()
            : true;
    }

    private function defaultMinutes(): int
    {
        return method_exists($this->settings, 'autoBlacklistDefaultMinutes')
            ? max(0, (int)$this->settings->autoBlacklistDefaultMinutes())
            : 120;
    }

    /**
     * Главное: один Cache::get максимум раз в минуту на процесс.
     *
     * @return array<int,int> [changer_id => unix_ts]
     */
    private function activeMapFast(): array
    {
        if ($this->localMap !== null && (time() - $this->localLoadedAt) < self::CACHE_TTL_SECONDS) {
            return $this->localMap;
        }

        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            $out = [];
            foreach ($cached as $k => $v) {
                $id = (int)$k;
                $ts = (int)$v;
                if ($id > 0 && $ts > 0) $out[$id] = $ts;
            }
            $this->localMap = $out;
            $this->localLoadedAt = time();
            return $out;
        }

        // cache miss: 1 SELECT
        $rows = BestChangeExchangerCooldown::query()
            ->where('blocked_until', '>', now())
            ->get(['changer_id','blocked_until']);

        $map = [];
        foreach ($rows as $r) {
            $id = (int)$r->changer_id;
            $ts = $r->blocked_until?->getTimestamp() ?? 0;
            if ($id > 0 && $ts > 0) $map[$id] = $ts;
        }

        Cache::put(self::CACHE_KEY, $map, self::CACHE_TTL_SECONDS);

        $this->localMap = $map;
        $this->localLoadedAt = time();

        return $map;
    }

    private function touchCacheLocal(int $changerId, int $untilTs): void
    {
        $map = $this->activeMapFast();
        $map[$changerId] = $untilTs;

        $this->localMap = $map;
        $this->localLoadedAt = time();

        Cache::put(self::CACHE_KEY, $map, self::CACHE_TTL_SECONDS);
    }

    /**
     * @param array<mixed> $meta
     * @return array<string,mixed>
     */
    private function normalizeMeta(array $meta): array
    {
        $out = [];
        foreach ($meta as $k => $v) {
            $key = trim((string)$k);
            if ($key === '') continue;
            if (is_scalar($v) || is_array($v) || $v === null) {
                $out[$key] = $v;
            }
        }
        return $out;
    }
}
