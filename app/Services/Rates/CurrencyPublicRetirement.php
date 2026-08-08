<?php

declare(strict_types=1);

namespace App\Services\Rates;

/**
 * C3-B: owner-approved currency public retirement (TUSDTRC20, DAI, FTN, A7A5).
 * Blocks new public catalog/quote/order/export without deleting rows.
 * ZELLEUSD is intentionally not listed here.
 */
final class CurrencyPublicRetirement
{
    /** @var array{ids:array<int,true>,codes:array<string,true>}|null */
    private static ?array $cache = null;

    public static function isCurrencyIdRetired(?int $currencyId): bool
    {
        if ($currencyId === null || $currencyId <= 0) {
            return false;
        }

        return isset(self::map()['ids'][$currencyId]);
    }

    public static function isDesignationRetired(?string $designationXml): bool
    {
        $code = strtoupper(trim((string) $designationXml));
        if ($code === '') {
            return false;
        }

        return isset(self::map()['codes'][$code]);
    }

    public static function directionTouchesRetired(?int $currency1Id, ?int $currency2Id, ?string $fromXml = null, ?string $toXml = null): bool
    {
        if (self::isCurrencyIdRetired($currency1Id) || self::isCurrencyIdRetired($currency2Id)) {
            return true;
        }

        return self::isDesignationRetired($fromXml) || self::isDesignationRetired($toXml);
    }

    /** @internal */
    public static function clearCache(): void
    {
        self::$cache = null;
    }

    /**
     * @return array{ids:array<int,true>,codes:array<string,true>}
     */
    private static function map(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = base_path('resources/rates/currency-public-retirement.json');
        $ids = [];
        $codes = [];
        if (is_file($path)) {
            $json = json_decode((string) file_get_contents($path), true);
            foreach (($json['retire_currency_ids'] ?? []) as $id) {
                $ids[(int) $id] = true;
            }
            foreach (($json['retire_designation_xml'] ?? []) as $code) {
                $codes[strtoupper(trim((string) $code))] = true;
            }
        }

        return self::$cache = ['ids' => $ids, 'codes' => $codes];
    }
}
