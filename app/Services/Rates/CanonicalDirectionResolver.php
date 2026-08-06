<?php

declare(strict_types=1);

namespace App\Services\Rates;

use App\Models\DirectionExchange;
use Illuminate\Support\Facades\Log;

/**
 * Deterministic direction resolution for explicit pair requests.
 *
 * Contexts: WEBSITE_DEEP_LINK, BESTCHANGE_LINK, QUOTE, ORDER, XML_EXPORT.
 *
 * Forbidden: remapping an explicit requested pair onto an unrelated default
 * (e.g. USDTTRC20→SBERRUB) when the request named a different destination.
 */
final class CanonicalDirectionResolver
{
    public const STATUS_EXACT_PAIR_FOUND = 'EXACT_PAIR_FOUND';

    public const STATUS_PAIR_ALIAS_RESOLVED = 'PAIR_ALIAS_RESOLVED';

    public const STATUS_PAIR_UNAVAILABLE = 'PAIR_UNAVAILABLE';

    public const STATUS_PAIR_INVALID = 'PAIR_INVALID';

    public const STATUS_AMBIGUOUS_PAIR = 'AMBIGUOUS_PAIR';

    /**
     * Public URL aliases that map to alternate designation_xml values.
     *
     * @var array<string, list<string>>
     */
    private const DESIGNATION_ALIASES = [
        'TON' => ['TON', 'GRAM'],
        'GRAM' => ['GRAM', 'TON'],
    ];

    /**
     * When multiple currency rows share designation_xml (location variants),
     * prefer this internal currency id for letter_cod-only resolution.
     *
     * @var array<string, int>
     */
    private const CANONICAL_CURRENCY_ID_BY_DESIGNATION = [
        // Cash USD AM is the public XML/website canonical; LA is a location variant.
        'CASHUSD' => 69,
    ];

    /**
     * @return array{
     *   status:string,
     *   direction_id:int|null,
     *   normalized_source:string,
     *   normalized_destination:string,
     *   alias_used:string|null,
     *   reason:string,
     *   currency1_id:int|null,
     *   currency2_id:int|null
     * }
     */
    public static function resolve(
        string $sourceIdentifier,
        string $destinationIdentifier,
        string $context = 'WEBSITE_DEEP_LINK',
        ?int $sourceCurrencyId = null,
        ?int $destinationCurrencyId = null,
    ): array {
        $from = self::normalizeCode($sourceIdentifier);
        $to = self::normalizeCode($destinationIdentifier);

        if ($from === null || $to === null) {
            return self::result(
                self::STATUS_PAIR_INVALID,
                null,
                (string) $sourceIdentifier,
                (string) $destinationIdentifier,
                null,
                'invalid_currency_code',
            );
        }

        $fromAliases = self::aliasesFor($from);
        $toAliases = self::aliasesFor($to);
        $aliasUsed = null;
        if ($fromAliases !== [$from] || $toAliases !== [$to]) {
            $aliasUsed = $from . '/' . $to;
        }

        $query = DirectionExchange::query()->quoteable();

        if ($sourceCurrencyId !== null && $destinationCurrencyId !== null) {
            $query->where([
                ['id_currency1', $sourceCurrencyId],
                ['id_currency2', $destinationCurrencyId],
            ]);
        } else {
            $canonicalFromId = self::CANONICAL_CURRENCY_ID_BY_DESIGNATION[$from] ?? null;
            $canonicalToId = self::CANONICAL_CURRENCY_ID_BY_DESIGNATION[$to] ?? null;

            $query->whereHas('currency1', function ($q) use ($fromAliases, $canonicalFromId) {
                $q->whereIn('designation_xml', $fromAliases);
                if ($canonicalFromId !== null) {
                    $q->where('id', $canonicalFromId);
                }
            })->whereHas('currency2', function ($q) use ($toAliases, $canonicalToId) {
                $q->whereIn('designation_xml', $toAliases);
                if ($canonicalToId !== null) {
                    $q->where('id', $canonicalToId);
                }
            });
        }

        $rows = $query->orderBy('id')->get();
        $eligible = $rows->filter(
            static fn (DirectionExchange $d) => !PublicDuplicateExclusion::isExcluded((int) $d->id)
        )->values();

        if ($eligible->isEmpty()) {
            return self::result(
                self::STATUS_PAIR_UNAVAILABLE,
                null,
                $from,
                $to,
                $aliasUsed,
                'no_quoteable_direction',
                null,
                null,
            );
        }

        // Distinct currency-id edges under the same public codes = ambiguity.
        $edgeKeys = $eligible->map(
            static fn (DirectionExchange $d) => (int) $d->id_currency1 . ':' . (int) $d->id_currency2
        )->unique()->values();

        if ($edgeKeys->count() > 1 && $sourceCurrencyId === null && $destinationCurrencyId === null) {
            Log::warning('canonical_direction_ambiguous', [
                'context' => $context,
                'from' => $from,
                'to' => $to,
                'edges' => $edgeKeys->all(),
                'direction_ids' => $eligible->pluck('id')->all(),
            ]);

            return self::result(
                self::STATUS_AMBIGUOUS_PAIR,
                null,
                $from,
                $to,
                $aliasUsed,
                'multiple_currency_edges',
            );
        }

        /** @var DirectionExchange $chosen */
        $chosen = $eligible->first();
        $status = $aliasUsed !== null
            ? self::STATUS_PAIR_ALIAS_RESOLVED
            : self::STATUS_EXACT_PAIR_FOUND;

        return self::result(
            $status,
            (int) $chosen->id,
            $from,
            $to,
            $aliasUsed,
            'ok',
            (int) $chosen->id_currency1,
            (int) $chosen->id_currency2,
        );
    }

    /**
     * @return list<string>
     */
    public static function aliasesFor(string $code): array
    {
        $upper = strtoupper($code);

        return self::DESIGNATION_ALIASES[$upper] ?? [$upper];
    }

    private static function normalizeCode(string $raw): ?string
    {
        $trimmed = trim($raw);
        if ($trimmed === '' || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,31}$/', $trimmed)) {
            return null;
        }

        return strtoupper($trimmed);
    }

    /**
     * @return array{
     *   status:string,
     *   direction_id:int|null,
     *   normalized_source:string,
     *   normalized_destination:string,
     *   alias_used:string|null,
     *   reason:string,
     *   currency1_id:int|null,
     *   currency2_id:int|null
     * }
     */
    private static function result(
        string $status,
        ?int $directionId,
        string $from,
        string $to,
        ?string $aliasUsed,
        string $reason,
        ?int $c1 = null,
        ?int $c2 = null,
    ): array {
        return [
            'status' => $status,
            'direction_id' => $directionId,
            'normalized_source' => $from,
            'normalized_destination' => $to,
            'alias_used' => $aliasUsed,
            'reason' => $reason,
            'currency1_id' => $c1,
            'currency2_id' => $c2,
        ];
    }
}
