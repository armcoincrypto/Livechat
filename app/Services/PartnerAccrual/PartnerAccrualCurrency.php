<?php

declare(strict_types=1);

namespace App\Services\PartnerAccrual;

/**
 * V1 partner accrual is USD-only.
 * Do NOT treat USDT as USD.
 */
final class PartnerAccrualCurrency
{
    public const V1_CODE = 'USD';

    public const AUDIT_SOURCE = 'partner_monthly_accrual';

    /** Display precision for product wording; storage uses DECIMAL(20,8). */
    public const USD_DISPLAY_PRECISION = 2;

    public const STORAGE_SCALE = 8;

    /**
     * @var list<string>
     */
    private const REJECTED_CODES = ['USDT', 'EUR', 'RUB', 'BTC', 'ETH'];

    public static function isSupported(string $currencyCode): bool
    {
        return strtoupper(trim($currencyCode)) === self::V1_CODE;
    }

    public static function assertSupported(string $currencyCode): void
    {
        $code = strtoupper(trim($currencyCode));
        if ($code !== self::V1_CODE) {
            throw Exceptions\CurrencyNotSupported::forCode($code);
        }
        if (in_array($code, self::REJECTED_CODES, true) && $code !== self::V1_CODE) {
            throw Exceptions\CurrencyNotSupported::forCode($code);
        }
    }

    public static function installmentReference(int $installmentId): string
    {
        return 'partner_accrual_installment:'.$installmentId;
    }

    public static function idempotencyKey(int $planId, int $installmentId): string
    {
        return 'partner_accrual:'.$planId.':'.$installmentId;
    }
}
