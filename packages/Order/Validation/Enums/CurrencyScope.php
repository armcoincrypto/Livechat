<?php
declare(strict_types=1);

namespace iEXPackages\Order\Validation\Enums;

enum CurrencyScope: string
{
    case IN = 'in';
    case OUT = 'out';

    public static function resolve(mixed $value, self $default = self::IN): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return match (strtolower(trim((string) $value))) {
            'out' => self::OUT,
            'in'  => self::IN,
            default => $default,
        };
    }
}
