<?php

declare(strict_types=1);

namespace App\Services\Orders;

/**
 * Shape + network-metadata checks for Kobbopay deposit destinations.
 * An EVM-looking address is not proof of the correct chain.
 */
final class KobbopayDepositAddressValidator
{
    public static function accept(string $address, ?string $expectedNetworkCode, ?string $providerToken): bool
    {
        $address = trim($address);
        if ($address === '') {
            return false;
        }

        $expected = strtoupper(trim((string) $expectedNetworkCode));
        $token = strtoupper(trim((string) $providerToken));

        if ($expected === '') {
            return false;
        }

        if ($token === '' || $token !== $expected) {
            return false;
        }

        $family = self::familyForNetwork($expected);
        if ($family === 'tron') {
            return self::isTronAddress($address);
        }
        if ($family === 'evm') {
            return self::isEvmAddress($address);
        }

        return false;
    }

    public static function familyForNetwork(string $networkCode): ?string
    {
        $code = strtoupper(trim($networkCode));

        return match ($code) {
            'USDTTRC' => 'tron',
            'USDTBSC', 'USDTERC', 'USDTPOLYGON', 'USDTMATIC' => 'evm',
            default => null,
        };
    }

    public static function isTronAddress(string $address): bool
    {
        $address = trim($address);
        if (strlen($address) !== 34 || ! str_starts_with($address, 'T')) {
            return false;
        }

        return (bool) preg_match('/^T[1-9A-HJ-NP-Za-km-z]{33}$/', $address);
    }

    public static function isEvmAddress(string $address): bool
    {
        return (bool) preg_match('/^0x[0-9a-fA-F]{40}$/', trim($address));
    }
}
