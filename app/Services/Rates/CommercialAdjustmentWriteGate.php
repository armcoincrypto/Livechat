<?php

declare(strict_types=1);

namespace App\Services\Rates;

/**
 * Explicit allow-list for mutating owner-controlled commercial adjustment fields
 * on automatic directions: profit, floating_fee, fix_fee.
 *
 * Unapproved writers must not silently overwrite admin «Прибыль».
 */
final class CommercialAdjustmentWriteGate
{
    public const EVENT_UNAUTHORIZED = 'UNAUTHORIZED_PROFIT_WRITE';

    /** @var list<string> */
    private static array $stack = [];

    public static function allow(string $writer): void
    {
        self::$stack[] = $writer;
    }

    public static function release(): void
    {
        array_pop(self::$stack);
    }

    /**
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public static function run(string $writer, callable $callback): mixed
    {
        self::allow($writer);
        try {
            return $callback();
        } finally {
            self::release();
        }
    }

    public static function currentWriter(): ?string
    {
        if (self::$stack === []) {
            return null;
        }

        return self::$stack[array_key_last(self::$stack)];
    }

    public static function isApproved(): bool
    {
        return self::currentWriter() !== null;
    }

    /**
     * Approved non-admin writers (BASE compilers that may neutralize profit=0 for ZELLE only).
     *
     * @return list<string>
     */
    public static function approvedWriters(): array
    {
        return [
            'admin:DirectionExchangeProfitController',
            'admin:DirectionExchangeController',
            'zelle:ZelleUsdUsdtBenchmarkAuthority',
            'defaults:DirectionCreationDefaults',
            'legacy:RatesApplyUsdtRubCommercialTargetCommand',
        ];
    }
}
