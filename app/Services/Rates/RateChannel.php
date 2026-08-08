<?php

declare(strict_types=1);

namespace App\Services\Rates;

enum RateChannel: string
{
    case Website = 'website';
    case Order = 'order';
    case Bestchange = 'bestchange';
}
