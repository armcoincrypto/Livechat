<?php
declare(strict_types=1);

namespace App\Services\Calculator;

class CalculatorMathConfig implements CalculatorMathConfigInterface
{
    public bool|string $allowArithmetic = true;
    public bool $allowPercentage = true;
    public bool $autoSubtractPercentage = false;
    public bool $autoSubtractNumbers = false;
    public int $decimalPlaces = 18;
    public string $minScaleThreshold = "0.00000001";
    public bool $preventNegativeBalance = true;
    private float $amount = 0.0;

    /**
     * Устанавливает опции конфигурации с валидацией.
     *
     * @param array $options Массив с опциями и их значениями
     * @return $this
     */
    public function setOptions(array $options): static
    {
        foreach ($options as $key => $value) {
            if (property_exists($this, $key)) {
                switch ($key) {
                    case 'decimalPlaces':
                        $this->decimalPlaces = max(0, (int)$value);
                        break;

                    case 'minScaleThreshold':
                        $this->minScaleThreshold = (string)$value;
                        break;

                    case 'allowArithmetic':
                        $this->allowArithmetic = is_bool($value) || $value === 'minus' ? $value : true;
                        break;

                    default:
                        $this->{$key} = (bool)$value;
                        break;
                }
            }
        }

        return $this;
    }
}
