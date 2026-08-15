<?php
declare(strict_types=1);

namespace iEXPackages\Order\Validation\Engine;

/**
 * Управление правилами “снаружи”.
 *
 * Примеры:
 * - only(['blacklist'])
 * - exclude(['blacklist'])
 */
final class ValidationSelector
{
    /** @var array<string,true> */
    private array $only = [];

    /** @var array<string,true> */
    private array $exclude = [];

    public function only(array $ids): self
    {
        $this->only = array_fill_keys($ids, true);
        return $this;
    }

    public function exclude(array $ids): self
    {
        $this->exclude = array_fill_keys($ids, true);
        return $this;
    }

    public function allows(ValidationStep $step): bool
    {
        if ($this->only !== [] && !isset($this->only[$step->id])) return false;
        if (isset($this->exclude[$step->id])) return false;
        return true;
    }
}
