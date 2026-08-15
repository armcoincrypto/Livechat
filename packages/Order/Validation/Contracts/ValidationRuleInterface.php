<?php
declare(strict_types=1);

namespace iEXPackages\Order\Validation\Contracts;

use iEXPackages\Order\Validation\Context\ValidationContext;
use iEXPackages\Order\Validation\Result\ValidationResult;

/**
 * Контракт правила валидации.
 *
 * Правило:
 * - НЕ пишет в БД
 * - НЕ использует request()/auth() напрямую
 * - Работает только с ValidationContext
 */
interface ValidationRuleInterface
{
    public function validate(ValidationContext $context): ValidationResult;
}
