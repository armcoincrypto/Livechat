<?php

declare(strict_types=1);

namespace App\Services\Rates;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Single ownership gate for public/outgoing ZELLEUSD direction rate rows.
 *
 * Default invariant: only {@see ZelleUsdUsdtBenchmarkAuthority} may write
 * course_value / manual_rate_value / parser_source_name / related rate metadata
 * on directions where id_currency1 = ZELLEUSD.
 *
 * Intentional override (2026-08-28 owner policy): when BestChange is the active
 * runtime owner (bestchange_directions.status=1 AND parser_source_name=BestChange),
 * BestChange may write and the Zelle benchmark must yield — one owner only.
 *
 * Generic compilers (DerivedMarketBaseline, crypto baseline retune, etc.) must
 * call {@see blocksGenericWrite()} / {@see denyGenericWrite()} and skip unless
 * {@see yieldsToBestChange()} is true for that direction.
 */
final class ZelleOutgoingRateWriteGuard
{
    public const ZELLE_CURRENCY_ID = ZelleUsdBenchmarkResolver::ZELLE_CURRENCY_ID;

    public const AUTHORITY = ZelleUsdUsdtBenchmarkAuthority::PARSER_SOURCE_NAME;

    /**
     * @var array<int, true>|null
     */
    private static ?array $zelleOutgoingIdCache = null;

    /**
     * @var array<int, bool>|null
     */
    private static ?array $bestChangeOwnedCache = null;

    public static function zelleCurrencyId(): int
    {
        return self::ZELLE_CURRENCY_ID;
    }

    public static function clearCache(): void
    {
        self::$zelleOutgoingIdCache = null;
        self::$bestChangeOwnedCache = null;
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
     * True when BestChange is the intentional runtime owner for this Zelle-out direction.
     */
    public static function yieldsToBestChange(?int $directionId): bool
    {
        if ($directionId === null || $directionId <= 0 || !self::isZelleOutgoingId($directionId)) {
            return false;
        }

        if (self::$bestChangeOwnedCache !== null && array_key_exists($directionId, self::$bestChangeOwnedCache)) {
            return self::$bestChangeOwnedCache[$directionId];
        }

        $parser = (string) (DB::table('direction_exchange')->where('id', $directionId)->value('parser_source_name') ?? '');
        $bcActive = DB::table('bestchange_directions')
            ->where('id_direction_exchange', $directionId)
            ->where('status', 1)
            ->exists();

        $yields = $bcActive && $parser === 'BestChange';
        self::$bestChangeOwnedCache ??= [];
        self::$bestChangeOwnedCache[$directionId] = $yields;

        return $yields;
    }

    /**
     * True when direction is crypto→ZELLEUSD already labeled BENCHMARK.
     */
    public static function isZelleIncomingBenchmarkId(?int $directionId): bool
    {
        if ($directionId === null || $directionId <= 0) {
            return false;
        }
        $row = DB::table('direction_exchange')->where('id', $directionId)->first([
            'id_currency1', 'id_currency2', 'parser_source_name',
        ]);
        if ($row === null) {
            return false;
        }
        if ((int) $row->id_currency2 !== self::ZELLE_CURRENCY_ID
            || (int) $row->id_currency1 === self::ZELLE_CURRENCY_ID
            || (int) $row->id_currency1 <= 0
        ) {
            return false;
        }

        return (string) ($row->parser_source_name ?? '') === self::AUTHORITY;
    }

    /**
     * Generic/derived writers must not mutate ZELLE outgoing rate fields,
     * unless BestChange is the active runtime owner (then BC/compiler may write).
     * Also blocks crypto→ZELLEUSD once labeled BENCHMARK.
     */
    public static function blocksGenericWrite(?int $directionId): bool
    {
        if (self::isZelleIncomingBenchmarkId($directionId)) {
            return true;
        }
        if (!self::isZelleOutgoingId($directionId)) {
            return false;
        }

        return !self::yieldsToBestChange($directionId);
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
