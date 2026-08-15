<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Blacklist\Enums;

/**
 * Где искать ключевое слово в базе BestChange.
 */
enum BlacklistWhere: string
{
    /** Искать только в контактах/кошельках */
    case Contacts = 'c';

    /** Искать только в описании */
    case Description = 'd';

    /** Искать и в контактах, и в описании */
    case ContactsAndDescription = 'cd';
}
