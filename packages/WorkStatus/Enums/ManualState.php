<?php

declare(strict_types=1);

namespace iEXPackages\WorkStatus\Enums;

/**
 * ManualState
 *
 * Состояние ручного режима.
 */
enum ManualState: string
{
    case Online  = 'online';
    case Offline = 'offline';

    public static function fromValue(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return match (strtolower(trim((string) $value))) {
            self::Offline->value, '0', 'false', 'off' => self::Offline,
            default => self::Online,
        };
    }
}
