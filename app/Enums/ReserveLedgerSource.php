<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * ReserveLedgerSource
 *
 * Источник операции по резерву.
 */
enum ReserveLedgerSource: string
{
    case TASK = 'task';       // заявка
    case ADMIN = 'admin';     // ручное действие администратора
    case CRON = 'cron';       // cron / job
    case SYSTEM = 'system';   // системное исправление
}
