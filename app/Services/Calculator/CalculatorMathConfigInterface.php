<?php
declare(strict_types=1);

namespace App\Services\Calculator;


interface CalculatorMathConfigInterface
{
    public function setOptions(array $options): static;
}
