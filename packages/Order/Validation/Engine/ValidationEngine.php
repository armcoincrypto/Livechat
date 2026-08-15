<?php
declare(strict_types=1);

namespace iEXPackages\Order\Validation\Engine;

use iEXPackages\Order\Validation\Context\ValidationContext;
use iEXPackages\Order\Validation\Result\ValidationResult;

/**
 * Движок выполнения пайплайна валидации.
 *
 * Гарантии:
 * - порядок по priority
 * - поддержка selector (only/exclude)
 * - stopOnError (прерывание при первой блокирующей ошибке)
 */
final class ValidationEngine
{
    public function run(
        ValidationContext $context,
        ValidationPipeline $pipeline,
        ?ValidationSelector $selector = null
    ): ValidationResult {
        $selector ??= new ValidationSelector();

        $steps = array_values(array_filter(
            $pipeline->all(),
            static fn(ValidationStep $s) => $selector->allows($s)
        ));

        usort($steps, static fn(ValidationStep $a, ValidationStep $b) => $a->priority <=> $b->priority);

        $result = ValidationResult::ok();

        foreach ($steps as $step) {
            $stepContext = $context->withOptions($step->options);

            $stepResult = $step->rule->validate($stepContext);
            $result->merge($stepResult);

            if ($step->stopOnError && $stepResult->hasErrors()) {
                break;
            }
        }

        return $result;
    }
}
