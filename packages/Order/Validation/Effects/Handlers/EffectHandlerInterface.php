<?php
declare(strict_types=1);

namespace iEXPackages\Order\Validation\Effects\Handlers;

use App\Models\Task;
use iEXPackages\Order\Validation\Enums\EffectType;

/**
 * Контракт обработчика эффекта валидации.
 *
 * Каждый эффект (FreezeScam/AmlSnapshot/CardInfoSnapshot и т.п.)
 * должен иметь свой класс-обработчик.
 *
 * EffectsApplier остаётся единым оркестратором.
 */
interface EffectHandlerInterface
{
    /**
     * Поддерживает ли обработчик указанный тип эффекта.
     */
    public function supports(EffectType $type): bool;

    /**
     * Применить эффект к уже созданной заявке.
     *
     * @param Task $order
     * @param array<string,mixed> $payload
     */
    public function handle(Task $order, array $payload): void;
}
