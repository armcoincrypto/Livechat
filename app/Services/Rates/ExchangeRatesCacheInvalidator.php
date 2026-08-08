<?php

declare(strict_types=1);

namespace App\Services\Rates;

use App\Services\CacheService;
use iEXPackages\Courses\CoursesFacade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Targeted invalidation + rebuild of public exchange rate/catalog Redis snapshots
 * after admin currency/direction mutations. Never flushes unrelated caches.
 *
 * IMPORTANT: forgetting keys without rebuilding leaves /rates/update as {} and
 * the owned frontend picker shows "Currency list unavailable". Always rebuild.
 */
final class ExchangeRatesCacheInvalidator
{
    /** @var list<string> */
    public const KEYS = [
        'exchange-iex-initial-rates-en',
        'exchange-iex-initial-rates-ru',
    ];

    public function __construct(private readonly CacheService $cache)
    {
    }

    /**
     * Forget known public rates snapshot keys, then rebuild EN/RU snapshots.
     * Safe if Redis/builder is down: committed admin writes are not rolled back.
     *
     * @return array{forgotten:int, attempted:int, errors:int, rebuilt:int}
     */
    public function invalidatePublicRatesSnapshots(string $reason = 'admin'): array
    {
        $forgotten = 0;
        $errors = 0;
        $attempted = count(self::KEYS);

        foreach (self::KEYS as $key) {
            try {
                if ($this->cache->forget($key)) {
                    $forgotten++;
                }
            } catch (Throwable $e) {
                $errors++;
                Log::warning('exchange_rates_cache_invalidate_failed', [
                    'key' => $key,
                    'reason' => $reason,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $rebuilt = $this->rebuildPublicRatesSnapshots($reason);

        Log::info('exchange_rates_cache_invalidate', [
            'reason' => $reason,
            'attempted' => $attempted,
            'forgotten' => $forgotten,
            'errors' => $errors,
            'rebuilt' => $rebuilt,
        ]);

        return [
            'forgotten' => $forgotten,
            'attempted' => $attempted,
            'errors' => $errors,
            'rebuilt' => $rebuilt,
        ];
    }

    /**
     * Rebuild public catalog snapshots the same way scheme:files does for directions.
     */
    public function rebuildPublicRatesSnapshots(string $reason = 'admin'): int
    {
        $rebuilt = 0;

        try {
            $rates = CoursesFacade::withData([]);
            foreach ($rates->availableLanguage() as $locale => $value) {
                $rates->setLocale($locale)->builder();
                $rebuilt++;
            }
            try {
                Cache::increment('dr_snapshot_id');
            } catch (Throwable) {
                // Non-fatal: snapshot id bump is best-effort.
            }
        } catch (Throwable $e) {
            Log::error('exchange_rates_cache_rebuild_failed', [
                'reason' => $reason,
                'message' => $e->getMessage(),
            ]);
        }

        return $rebuilt;
    }

    /** Schedule invalidation+rebuild after the DB transaction commits (or immediately). */
    public function afterCommit(string $reason = 'admin'): void
    {
        if (function_exists('app') && app()->bound('db')) {
            try {
                \Illuminate\Support\Facades\DB::afterCommit(function () use ($reason): void {
                    $this->invalidatePublicRatesSnapshots($reason);
                });

                return;
            } catch (Throwable) {
                // Fall through to immediate invalidation.
            }
        }

        $this->invalidatePublicRatesSnapshots($reason);
    }
}
