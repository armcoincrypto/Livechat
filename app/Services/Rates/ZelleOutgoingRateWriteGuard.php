<?php

declare(strict_types=1);

namespace App\Services\Rates;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Single ownership gate for public/outgoing ZELLEUSD direction rate rows.
 *
 * Invariant: only {@see ZelleUsdUsdtBenchmarkAuthority} may write
 * course_value / manual_rate_value / parser_source_name / related rate metadata
 * on directions where id_currency1 = ZELLEUSD.
 *
 * Generic compilers (DerivedMarketBaseline, crypto baseline retune, BestChange
 * recalculate, etc.) must call {@see blocksGenericWrite()} / {@see assertNotZelleOutgoing()}
 * and skip. They may still READ upstream market data used by the ZELLE resolver.
 */
final class ZelleOutgoingRateWriteGuard
{
    public const ZELLE_CURRENCY_ID = ZelleUsdBenchmarkResolver::ZELLE_CURRENCY_ID;

    public const AUTHORITY = ZelleUsdUsdtBenchmarkAuthority::PARSER_SOURCE_NAME;

    /**
     * @var array<int, true>|null
     */
    private static ?array $zelleOutgoingIdCache = null;

    public static function zelleCurrencyId(): int
    {
        return self::ZELLE_CURRENCY_ID;
    }

    public static function clearCache(): void
    {
        self::$zelleOutgoingIdCache = null;
    }

    public static function isZelleOutgoingId(?int $directionId): bool
    {
        if ($directionId === null || $directionId <= 0) {
            return false;
        }

        return isset(self::zelleOutgoingIdMap()[$directionId]);
    }

    public static function isZelleOutgoingDirection(?object $direction): bool
    {
        if ($direction === null) {
            return false;
        }

        $currency1 = (int) ($direction->id_currency1 ?? 0);
        if ($currency1 === self::ZELLE_CURRENCY_ID) {
            return true;
        }

        $id = (int) ($direction->id ?? 0);

        return $id > 0 && self::isZelleOutgoingId($id);
    }

    /**
     * Generic/derived/compiler writers must not mutate ZELLE outgoing rate fields.
     */
    public static function blocksGenericWrite(?int $directionId): bool
    {
        return self::isZelleOutgoingId($directionId);
    }

    /**
     * @return array{blocked:bool,reason:?string,direction_id:?int}
     */
    public static function denyGenericWrite(?int $directionId, string $writer): array
    {
        if (!self::blocksGenericWrite($directionId)) {
            return [
                'blocked' => false,
                'reason' => null,
                'direction_id' => $directionId,
            ];
        }

        Log::info('zelle_outgoing_write_blocked', [
            'writer' => $writer,
            'direction_id' => $directionId,
            'authority' => self::AUTHORITY,
        ]);

        return [
            'blocked' => true,
            'reason' => 'zelle_outgoing_owned_by_'.self::AUTHORITY,
            'direction_id' => $directionId,
        ];
    }

    /**
     * @return array<int, true>
     */
    private static function zelleOutgoingIdMap(): array
    {
        if (self::$zelleOutgoingIdCache !== null) {
            return self::$zelleOutgoingIdCache;
        }

        $ids = DB::table('direction_exchange')
            ->where('id_currency1', self::ZELLE_CURRENCY_ID)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $map = [];
        foreach ($ids as $id) {
            $map[$id] = true;
        }

        return self::$zelleOutgoingIdCache = $map;
    }
}
