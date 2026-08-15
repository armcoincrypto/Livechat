<?php

declare(strict_types=1);

namespace iEXPackages\WorkStatus\Enums;

/**
 * OverrideMode
 *
 * Режим ручного перекрытия поверх расписания.
 */
enum OverrideMode: string
{
    case None         = 'none';
    case ForceOnline  = 'force_online';
    case ForceOffline = 'force_offline';

    public static function fromValue(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return match (strtolower(trim((string) $value))) {
            self::ForceOnline->value  => self::ForceOnline,
            self::ForceOffline->value => self::ForceOffline,
            default => self::None,
        };
    }
}
