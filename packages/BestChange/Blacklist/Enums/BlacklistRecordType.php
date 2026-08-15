<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Blacklist\Enums;

/**
 * Среди каких типов записей искать.
 */
enum BlacklistRecordType: string
{
    /** Только мошенники */
    case ScamOnly = 's';

    /** Только неадекваты */
    case InadequateOnly = 'c';

    /** Мошенники + неадекваты */
    case ScamAndInadequate = 'sc';
}
