<?php
declare(strict_types=1);

namespace iEXPackages\Order\Validation\Effects;

use App\Models\Task;
use iEXPackages\Order\Validation\Enums\EffectType;
use iEXPackages\Order\Validation\Effects\Handlers\EffectHandlerInterface;
use Illuminate\Support\Facades\Log;

/**
 * EffectsApplier — единая точка применения эффектов к созданной заявке (Task).
 *
 * Валидация только накапливает эффекты (ValidationResult::addEffect),
 * а реальное применение (DB write) происходит здесь через обработчики.
 *
 * Архитектура:
 * - 1 EffectType -> 1 handler (рекомендуется)
 * - apply() выполняет эффекты последовательно в том порядке, в котором они пришли
 */
final class EffectsApplier
{
    /** @var array<string, EffectHandlerInterface> key = EffectType->value */
    private array $map = [];

    /**
     * @param EffectHandlerInterface[] $handlers
     */
    public function __construct(array $handlers)
    {
        // Строим быстрый lookup: EffectType => handler
        foreach ($handlers as $handler) {
            if (!$handler instanceof EffectHandlerInterface) {
                continue;
            }

            foreach (EffectType::cases() as $type) {
                if (!$handler->supports($type)) {
                    continue;
                }

                $key = $type->value;

                // Если хочешь строго запретить два handler на один тип — включи warning
                if (isset($this->map[$key])) {
                    Log::warning('Multiple effect handlers registered for one type; keeping the first', [
                        'type' => $key,
                        'existing' => get_class($this->map[$key]),
                        'ignored' => get_class($handler),
                    ]);
                    continue;
                }

                $this->map[$key] = $handler;
            }
        }
    }

    /**
     * Применить эффекты к заявке.
     *
     * @param Task $order
     * @param array<int, array{type:EffectType, payload:array}> $effects
     */
    public function apply(Task $order, array $effects): void
    {
        foreach ($effects as $effect) {
            $type = $effect['type'] ?? null;

            if (!$type instanceof EffectType) {
                continue;
            }

            $payload = $effect['payload'] ?? [];
            if (!is_array($payload)) {
                $payload = [];
            }

            $handler = $this->map[$type->value] ?? null;

            if (!$handler) {
                Log::warning('No effect handler found', [
                    'order_id' => $order->id,
                    'type' => $type->value,
                ]);
                continue;
            }

            try {
                $handler->handle($order, $payload);
            } catch (\Throwable $e) {
                // Важно: не падаем целиком из-за одного эффекта
                Log::error('Effect handler failed', [
                    'order_id' => $order->id,
                    'type' => $type->value,
                    'handler' => get_class($handler),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
