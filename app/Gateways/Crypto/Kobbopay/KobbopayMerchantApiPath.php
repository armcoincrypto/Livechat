<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Kobbopay;

/**
 * Joins Kobbopay merchant API paths to the configured host without /api/api.
 *
 * Live vault base is https://merchant.kobbex.com (no /api). Invoice create already
 * uses /api/crypto/invoice/create. Health/options must use the same /api prefix.
 */
final class KobbopayMerchantApiPath
{
    public const TOKEN_FETCH = '/api/user/token/fetch';

    public const INVOICE_CREATE = '/api/crypto/invoice/create';

    public static function resolve(string $baseUrl, string $path): string
    {
        $path = '/' . ltrim($path, '/');
        $base = rtrim($baseUrl, '/');

        if (str_ends_with(strtolower($base), '/api') && str_starts_with($path, '/api/')) {
            return substr($path, 4);
        }

        return $path;
    }

    public static function tokenFetch(string $baseUrl): string
    {
        return self::resolve($baseUrl, self::TOKEN_FETCH);
    }
}
