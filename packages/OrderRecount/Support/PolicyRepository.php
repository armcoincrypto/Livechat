<?php

declare(strict_types=1);

namespace iEXPackages\OrderRecount\Support;

use App\Models\Task;
use iEXPackages\OrderRecount\Models\OrderRecountPolicy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * PolicyRepository
 *
 * Назначение:
 * - Подбирает применимые политики пересчёта для заявки (global + currency).
 * - Отдаёт список статусов для cron-сканера (быстро, с кэшем).
 */
final class PolicyRepository
{
    /**
     * Получить политики, которые применимы к заявке:
     * - global (scope_type=global, scope_id IS NULL)
     * - currency (scope_type=currency, scope_id=currency1.id)
     *
     * Приоритет:
     * - сортировка по priority ASC, затем по id ASC
     *
     * @return Collection<int, OrderRecountPolicy>
     */
    public function forTask(Task $task): Collection
    {
        $currencyId = (int) ($task->direction_exchange?->currency1?->id ?? 0);

        return OrderRecountPolicy::query()
            ->where('is_enabled', 1)
            ->where(function ($q) use ($currencyId): void {
                // global
                $q->where(function ($qq): void {
                    $qq->where('scope_type', 'global')->whereNull('scope_id');
                });

                // currency
                if ($currencyId > 0) {
                    $q->orWhere(function ($qq) use ($currencyId): void {
                        $qq->where('scope_type', 'currency')->where('scope_id', $currencyId);
                    });
                }
            })
            ->orderBy('priority')
            ->orderBy('id')
            ->get();
    }

    /**
     * Список статусов, которые нужно сканировать по cron-политикам.
     *
     * Оптимизация:
     * - кэшируем результат на короткий TTL, чтобы не парсить policies каждую минуту.
     *
     * @return int[]
     */
    public function cronStatuses(): array
    {
        $store = (string) config('order-recount.cache.store', config('cache.default'));
        $ttl   = (int) config('order-recount.cache.cron_statuses_ttl_seconds', 60);
        $key   = (string) config('order-recount.cache.cron_statuses_key', 'order-recount:cron-statuses:v1');

        return Cache::store($store)->remember($key, $ttl, function (): array {
            $policies = OrderRecountPolicy::query()
                ->where('is_enabled', 1)
                ->whereJsonContains('trigger_types', 'cron')
                ->get(['conditions']);

            $set = [];

            foreach ($policies as $p) {
                foreach ((array) $p->conditions as $cond) {
                    if (!is_array($cond)) continue;
                    if (($cond['type'] ?? '') !== 'status_in') continue;

                    foreach ((array) ($cond['value'] ?? []) as $v) {
                        $i = (int) $v;
                        if ($i > 0) {
                            $set[$i] = true;
                        }
                    }
                }
            }

            $out = array_keys($set);
            sort($out);

            return $out;
        });
    }
}
