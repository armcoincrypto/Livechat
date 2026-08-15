<?php
declare(strict_types=1);

namespace iEXPackages\Order\Validation\Engine;

/**
 * Пайплайн — упорядоченный набор шагов валидации.
 */
final class ValidationPipeline
{
    /** @var ValidationStep[] */
    private array $steps = [];

    /**
     * @param ValidationStep[] $steps
     */
    public function __construct(array $steps = [])
    {
        foreach ($steps as $s) {
            $this->add($s);
        }
    }

    public function add(ValidationStep $step): void
    {
        $this->steps[] = $step;
    }

    /**
     * @return ValidationStep[]
     */
    public function all(): array
    {
        return $this->steps;
    }
}
