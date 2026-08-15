<?php

namespace iEXPackages\Payment;

use Illuminate\Support\Facades\Facade;

class PaymentFacade extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'gateways';
    }
}
