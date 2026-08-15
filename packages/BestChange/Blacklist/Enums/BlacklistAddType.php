<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Blacklist\Enums;

/**
 * Тип записи при добавлении.
 */
enum BlacklistAddType: string
{
    /** Добавить мошенника */
    case Scam = 's';

    /** Добавить неадеквата */
    case Inadequate = 'c';
}
