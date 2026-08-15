<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\Currency;
use App\Models\DirectionExchange;
use App\Models\GatewayMerchant;
use App\Models\Requisites;
use App\Services\Rates\ZelleUsdBenchmarkResolver;

/**
 * Canonical inbound payment-destination ownership.
 *
 * Kobbopay: USDT TRC20 / BEP20 / ERC20 / Polygon (when the rail exists).
 * Zelle SEND: existing account verification (is_verify_account).
 * Everything else: admin Requisites / RequisiteManager.
 */
final class PaymentDestinationRouter
{
    public const OWNER_KOBBOPAY = 'KOBBOPAY';
    public const OWNER_ZELLE_VERIFICATION = 'ZELLE_VERIFICATION';
    public const OWNER_EXSWAPING_REQUISITE = 'EXSWAPING_REQUISITE';
    public const OWNER_UNSUPPORTED = 'UNSUPPORTED/UNKNOWN';

    public const ALIAS_KOBBOPAY = 'kobbopay';

    /**
     * Production letter_cod values proven from currencies.designation_xml.
     * Polygon is reserved for a future currency; it is not invented here.
     *
     * @var list<string>
     */
    public const KOBBOPAY_USDT_LETTER_CODS = [
        'USDTTRC20',
        'USDTBEP20',
        'USDTERC20',
        'USDTPOLYGON',
        'USDTMATIC',
    ];

    /**
     * Expected Kobbopay `token` / currency.network_code for each letter_cod.
     *
     * @var array<string, string>
     */
    public const KOBBOPAY_NETWORK_BY_LETTER_COD = [
        'USDTTRC20' => 'USDTTRC',
        'USDTBEP20' => 'USDTBSC',
        'USDTERC20' => 'USDTERC',
        'USDTPOLYGON' => 'USDTPOLYGON',
        'USDTMATIC' => 'USDTMATIC',
    ];

    public static function classifyDirection(?DirectionExchange $direction): string
    {
        if ($direction === null) {
            return self::OWNER_UNSUPPORTED;
        }

        $direction->loadMissing('currency1');

        return self::classifyCurrency($direction->currency1);
    }

    public static function classifyCurrency(?Currency $currency): string
    {
        if ($currency === null) {
            return self::OWNER_UNSUPPORTED;
        }

        if (self::isZelleInboundCurrency($currency)) {
            return self::OWNER_ZELLE_VERIFICATION;
        }

        if (self::isKobbopayInboundCurrency($currency)) {
            return self::OWNER_KOBBOPAY;
        }

        $xml = strtoupper(trim((string) ($currency->designation_xml ?? '')));
        if ($xml === '') {
            return self::OWNER_UNSUPPORTED;
        }

        return self::OWNER_EXSWAPING_REQUISITE;
    }

    public static function isZelleInbound(?DirectionExchange $direction): bool
    {
        if ($direction === null) {
            return false;
        }

        return (int) ($direction->id_currency1 ?? 0) === ZelleUsdBenchmarkResolver::ZELLE_CURRENCY_ID;
    }

    public static function isZelleInboundCurrency(?Currency $currency): bool
    {
        if ($currency === null) {
            return false;
        }

        if ((int) $currency->id === ZelleUsdBenchmarkResolver::ZELLE_CURRENCY_ID) {
            return true;
        }

        return strtoupper(trim((string) ($currency->designation_xml ?? ''))) === 'ZELLEUSD';
    }

    public static function isKobbopayInbound(?DirectionExchange $direction): bool
    {
        if ($direction === null) {
            return false;
        }

        $direction->loadMissing('currency1');

        return self::isKobbopayInboundCurrency($direction->currency1);
    }

    public static function isKobbopayInboundCurrency(?Currency $currency): bool
    {
        if ($currency === null) {
            return false;
        }

        $xml = strtoupper(trim((string) ($currency->designation_xml ?? '')));

        return in_array($xml, self::KOBBOPAY_USDT_LETTER_CODS, true);
    }

    public static function expectedKobbopayNetworkCode(?Currency $currency): ?string
    {
        if ($currency === null) {
            return null;
        }

        $xml = strtoupper(trim((string) ($currency->designation_xml ?? '')));
        $mapped = self::KOBBOPAY_NETWORK_BY_LETTER_COD[$xml] ?? null;
        if (is_string($mapped) && $mapped !== '') {
            return $mapped;
        }

        $fromCurrency = strtoupper(trim((string) ($currency->network_code ?? '')));

        return $fromCurrency !== '' ? $fromCurrency : null;
    }

    public static function hasActiveKobbopayMerchant(DirectionExchange $direction): bool
    {
        $direction->loadMissing([
            'currency1.merchants',
            'merchants',
        ]);

        $match = static function ($merchant): bool {
            return (int) ($merchant->status ?? 0) === 1
                && strtolower(trim((string) ($merchant->alias ?? ''))) === self::ALIAS_KOBBOPAY;
        };

        if ($direction->merchants->first($match) !== null) {
            return true;
        }

        $in = $direction->currency1;
        if ($in === null) {
            return false;
        }

        return $in->merchants->first($match) !== null;
    }

    public static function isActiveKobbopayMerchant(?GatewayMerchant $merchant): bool
    {
        if ($merchant === null) {
            return false;
        }

        return (int) ($merchant->status ?? 0) === 1
            && strtolower(trim((string) ($merchant->alias ?? ''))) === self::ALIAS_KOBBOPAY;
    }

    public static function hasCanonicalRequisiteSource(DirectionExchange $direction): bool
    {
        $direction->loadMissing([
            'currency1',
            'direction_requisites',
        ]);

        $in = $direction->currency1;
        if ($in === null) {
            return false;
        }

        if ((int) ($direction->method_request_payment ?? 0) === 1) {
            return true;
        }
        if ((int) ($in->method_request_payment ?? 0) === 1) {
            return true;
        }

        if ($direction->direction_requisites->where('status', 1)->isNotEmpty()) {
            return true;
        }

        return Requisites::activeWallet()->where('id_currency', $in->id)->exists();
    }

    public static function verificationUrl(?string $locale = null): string
    {
        $loc = strtolower(trim((string) $locale));
        if (! in_array($loc, ['en', 'ru'], true)) {
            $loc = 'en';
        }

        $base = rtrim((string) config('app.frontend_url', 'https://exswaping.com'), '/');

        return $base.'/'.$loc.'/account/user-verify';
    }
}
