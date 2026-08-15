<?php
declare(strict_types=1);

namespace iEXPackages\Order\Validation\Engine;

use iEXPackages\Order\Validation\Contracts\ValidationRuleInterface;

/**
 * Один шаг пайплайна валидации.
 *
 * Типизированный шаг защищает от ошибок вида:
 * - опечаток в ключах массива
 * - отсутствующих параметров
 * - случайной передачи не-правила
 */
final class ValidationStep
{
    public function __construct(
        public readonly string $id,
        public readonly ValidationRuleInterface $rule,
        public readonly int $priority,
        public readonly bool $stopOnError,
        public readonly array $options = []
    ) {}
}
