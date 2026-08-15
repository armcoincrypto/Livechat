<?php

namespace iEXPackages\Payment\Engines\Interfaces;

interface APIRequestInterface
{
    public function getBalance(string $currency): float|int;

    public function transfer(array $options = []): mixed;

    public function apiRequest(string $url, array $params = []): mixed;
}
