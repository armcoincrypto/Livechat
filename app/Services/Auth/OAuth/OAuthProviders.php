<?php

declare(strict_types=1);

namespace App\Services\Auth\OAuth;

final class OAuthProviders
{
    public const GOOGLE = 'google';
    public const YANDEX = 'yandex';

    /** @return list<string> */
    public static function supported(): array
    {
        return [self::GOOGLE, self::YANDEX];
    }

    public static function isSupported(string $provider): bool
    {
        return in_array(strtolower($provider), self::supported(), true);
    }

    public static function normalize(string $provider): string
    {
        return strtolower(trim($provider));
    }
}
