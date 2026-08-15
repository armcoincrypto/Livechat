<?php

namespace iEXPackages\Order\Invoices;

/**
 * Тип источника реквизитов.
 */
enum RequisitesMode: string
{
    case Merchant = 'merchant';
    case Request  = 'request';
    case Manual   = 'manual';
}
