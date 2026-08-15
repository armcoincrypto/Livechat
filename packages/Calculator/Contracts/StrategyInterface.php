<?php

namespace iEXPackages\Calculator\Contracts;

interface StrategyInterface
{
    public function getRate(): float|string;
}
