<?php
declare(strict_types=1);

namespace iEXPackages\Order\Validation\Effects\Handlers;

use App\Models\Task;
use iEXPackages\Order\Validation\Enums\EffectType;
use Illuminate\Support\Facades\Log;

/**
 * FreezeScamEffectHandler
 *
 * - ставит флаги на Task (is_freeze_scam + typeFreezeScam)
 * - пишет детали в tasks_meta.freeze_scam (если колонка добавлена миграцией)
 *
 * Идемпотентность:
 * - не перетираем уже установленный freeze_scam в meta
 */
final class FreezeScamEffectHandler implements EffectHandlerInterface
{
    public function supports(EffectType $type): bool
    {
        return $type === EffectType::FreezeScam;
    }

    public function handle(Task $order, array $payload): void
    {
        $type = trim((string)($payload['type'] ?? 'unknown'));
        if ($type === '') {
            $type = 'unknown';
        }

        $order->task_info()->update([
            'is_freeze_scam'   => 1
        ]);

        if (!method_exists($order, 'meta')) {
            return;
        }

        try {
            $meta = $order->meta()->first();

            // если уже есть freeze_scam — не перетираем
            if ($meta && !empty($meta->freeze_scam)) {
                return;
            }

            $order->meta()->updateOrCreate([], [
                'freeze_scam' => [
                    'type' => $type,
                    'hits' => is_array($payload['hits'] ?? null) ? ($payload['hits'] ?? []) : [],
                    'at'   => now()->toISOString(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('FreezeScamEffectHandler failed: ' . $e->getMessage(), [
                'order_id' => $order->id,
            ]);
        }
    }
}
