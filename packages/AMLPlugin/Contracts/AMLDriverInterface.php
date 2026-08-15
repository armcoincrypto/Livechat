<?php

namespace iEXPackages\AMLPlugin\Contracts;

interface AMLDriverInterface
{
    public function checkTransaction(array $params): AMLResponseInterface;

    public function checkAddress(array $params): AMLResponseInterface;
}
