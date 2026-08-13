<?php

declare(strict_types=1);

namespace App\Services\Rates;

use App\Models\DirectionExchange;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Deterministic direction resolution for explicit pair requests.
 *
 * Contexts: WEBSITE_DEEP_LINK, BESTCHANGE_LINK, QUOTE, ORDER, XML_EXPORT.
 *
 * Forbidden: remapping an explicit requested pair onto an unrelated default
 * (e.g. USDTTRC20→SBERRUB) when the request named a different destination.
 *
 * Multi-edge public codes (CARDAMD banks, CASHUSD city rails):
 * - Exact currency ids (fromId/toId) always win.
 * - Otherwise pick one deterministic quoteable edge for the same public codes.
 * - Never 404 a BestChange/XML-exported identity solely because multiple
 *   operational banks/cities share designation_xml.
 *
 * Resolution order for letter_cod-only deep links:
 * 1) BestChange public-identity allowlist direction_id (CARDAMD)
 * 2) Preferred canonical currency id edge when present (CASHUSD AM=69)
 * 3) Lowest direction id among remaining quoteable edges
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
     * Soft preference only (not a hard filter): when multiple currency rows
     * share designation_xml, prefer this id if that edge exists for the pair.
     *
     * @var array<string, int>
     */
    private const PREFERRED_CURRENCY_ID_BY_DESIGNATION = [
        // Cash USD AM is the preferred website identity when both AM+LA exist.
        'CASHUSD' => 69,
    ];

    /** @var array<string, mixed>|null */
    private static ?array $bestChangePublicDirections = null;

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
            $query->whereHas('currency1', function ($q) use ($fromAliases) {
                $q->whereIn('designation_xml', $fromAliases);
            })->whereHas('currency2', function ($q) use ($toAliases) {
                $q->whereIn('designation_xml', $toAliases);
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

        // Distinct currency-id edges under the same public codes.
        $edgeKeys = $eligible->map(
            static fn (DirectionExchange $d) => (int) $d->id_currency1 . ':' . (int) $d->id_currency2
        )->unique()->values();

        if ($edgeKeys->count() > 1 && $sourceCurrencyId === null && $destinationCurrencyId === null) {
            $chosen = self::pickDeterministicEdge($eligible, $from, $to, $context);
            if ($chosen === null) {
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

            return self::result(
                self::STATUS_PAIR_ALIAS_RESOLVED,
                (int) $chosen->id,
                $from,
                $to,
                $aliasUsed ?? ($from . '/' . $to),
                'deterministic_multi_edge',
                (int) $chosen->id_currency1,
                (int) $chosen->id_currency2,
            );
        }

        /** @var DirectionExchange $chosen */
        $chosen = $eligible->first();

        // Soft-prefer canonical currency id when a single edge was not forced by ids
        // but multiple rows were collapsed earlier — already handled above.
        // When exactly one edge exists (e.g. BTC→CASHUSD LA-only), accept it even if
        // preferred CASHUSD id is AM.
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
     * @param  Collection<int, DirectionExchange>  $eligible
     */
    private static function pickDeterministicEdge(
        Collection $eligible,
        string $from,
        string $to,
        string $context,
    ): ?DirectionExchange {
        unset($context);

        $pairKey = $from . '->' . $to;
        $allowlistId = self::bestChangeAllowlistDirectionId($pairKey);
        if ($allowlistId !== null) {
            $hit = $eligible->first(
                static fn (DirectionExchange $d) => (int) $d->id === $allowlistId
            );
            if ($hit !== null) {
                return $hit;
            }
        }

        $preferredFrom = self::PREFERRED_CURRENCY_ID_BY_DESIGNATION[$from] ?? null;
        $preferredTo = self::PREFERRED_CURRENCY_ID_BY_DESIGNATION[$to] ?? null;
        if ($preferredFrom !== null || $preferredTo !== null) {
            $preferred = $eligible->first(static function (DirectionExchange $d) use ($preferredFrom, $preferredTo) {
                if ($preferredFrom !== null && (int) $d->id_currency1 !== $preferredFrom) {
                    return false;
                }
                if ($preferredTo !== null && (int) $d->id_currency2 !== $preferredTo) {
                    return false;
                }

                return true;
            });
            if ($preferred !== null) {
                return $preferred;
            }
        }

        // Lowest direction id — stable, matches historical claimCanonicalPublicPair bias.
        return $eligible->sortBy(static fn (DirectionExchange $d) => (int) $d->id)->first();
    }

    private static function bestChangeAllowlistDirectionId(string $pairKey): ?int
    {
        $config = self::bestChangePublicDirectionsConfig();
        $byPair = $config['identities']['CARDAMD']['direction_ids_by_pair'] ?? [];
        if (!is_array($byPair)) {
            return null;
        }
        $id = $byPair[$pairKey] ?? null;

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function bestChangePublicDirectionsConfig(): array
    {
        if (self::$bestChangePublicDirections !== null) {
            return self::$bestChangePublicDirections;
        }

        $path = base_path('resources/rates/bestchange-public-directions.json');
        if (!is_file($path)) {
            return self::$bestChangePublicDirections = [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return self::$bestChangePublicDirections = is_array($decoded) ? $decoded : [];
    }

    /** @internal testing */
    public static function clearCaches(): void
    {
        self::$bestChangePublicDirections = null;
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
