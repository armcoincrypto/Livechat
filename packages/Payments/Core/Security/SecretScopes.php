<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Security;

final class SecretScopes
{
    private function __construct() {}

    public const MERCHANT = 'merchant';
    public const PAY      = 'pay';

    // расширяемое: webhook/system/admin
}
