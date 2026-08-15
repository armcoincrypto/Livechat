<?php
declare(strict_types=1);

namespace iEXPackages\Order\Validation\Enums;

/**
 * Уровень критичности результата проверки.
 */
enum Severity: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Info = 'info';
}
