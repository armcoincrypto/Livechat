<?php

namespace iEXPackages\Order\Facades;

use Illuminate\Support\Facades\Facade;

class OrderInvoiceFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'order.invoice';
    }
}
