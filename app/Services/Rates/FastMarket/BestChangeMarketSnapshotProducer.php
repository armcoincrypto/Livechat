<?php

declare(strict_types=1);

namespace App\Services\Rates\FastMarket;

use App\Models\BestChangeDirection;
use App\Services\AggregatedRatesService;
use App\Settings\BestChangeConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use iEXPackages\BestChange\Contracts\BestChangeHttpClientInterface;
use iEXPackages\BestChange\Services\BestChangeCatalogRepository;
use iEXPackages\BestChange\Services\PairKeyFactory;
use Throwable;

/**
 * Lightweight BestChange market snapshot producer.
 *
 * Fetches /rates in one batch (same client + pair index as RatesUpdateService)
 * and publishes an immutable shared snapshot. Does NOT recalculate or persist
 * directions (FULL_DIRECTION_RECALC_PER_REFRESH=NO).
 */
final class BestChangeMarketSnapshotProducer
{
    public function __construct(
        private readonly BestChangeConfig $settings,
        private readonly BestChangeHttpClientInterface $httpClient,
        private readonly BestChangeCatalogRepository $catalog,
        private readonly PairKeyFactory $pairKeyFactory,
        private readonly AggregatedRatesService $externalRatesService,
        private readonly MarketSnapshotStore $store,
    ) {}

    /**
     * @return array{ok: bool, snapshot: ?MarketSnapshot, error: ?string, skipped: bool}
     */
    public function refresh(bool $force = false): array
    {
        if (! FastMarketFlags::planeEnabled() && ! $force) {
            return ['ok' => false, 'snapshot' => null, 'error' => 'plane_disabled', 'skipped' => true];
        }
        if (! $this->settings->isEnabled()) {
            return ['ok' => false, 'snapshot' => null, 'error' => 'bestchange_disabled', 'skipped' => true];
        }

        $lock = null;
        try {
            $lock = Cache::lock(MarketSnapshotStore::LOCK_KEY, FastMarketFlags::producerLockSeconds());
            if (! $lock->get()) {
                $existing = $this->store->get();

                return [
                    'ok' => $existing !== null,
                    'snapshot' => $existing,
                    'error' => 'refresh_in_progress',
                    'skipped' => true,
                ];
            }
        } catch (Throwable) {
            $lock = null;
        }

        $t0 = (int) floor(microtime(true) * 1000);
        try {
            $this->catalog->warmupAll(false);
            $externalRates = $this->externalRatesService->fetch();
            $directions = $this->loadActiveDirections();
            [$pairKeys, $bcIdToPairKey] = $this->buildPairsIndex($directions);

            $pairByDeId = [];
            $bcIdByDeId = [];
            foreach ($directions as $direction) {
                $bcId = (int) $direction->id;
                $deId = (int) ($direction->id_direction_exchange ?? 0);
                $pairKey = $bcIdToPairKey[$bcId] ?? null;
                if ($deId <= 0 || $pairKey === null) {
                    continue;
                }
                $pairByDeId[$deId] = $pairKey;
                $bcIdByDeId[$deId] = $bcId;
            }

            if ($pairKeys === []) {
                return ['ok' => false, 'snapshot' => null, 'error' => 'no_pairs', 'skipped' => false];
            }

            $fetchStarted = (int) floor(microtime(true) * 1000);
            try {
                $ratesMap = $this->httpClient->fetchRatesBatch($pairKeys);
            } catch (Throwable $e) {
                Log::warning('fast_market_snapshot_fetch_failed', ['message' => $e->getMessage()]);

                return ['ok' => false, 'snapshot' => null, 'error' => 'fetch_failed', 'skipped' => false];
            }
            $fetchMs = (int) floor(microtime(true) * 1000) - $fetchStarted;

            $exchangersMap = $this->buildExchangersMap();
            $normalizedAt = time();
            $fetchedAt = $normalizedAt;
            $fingerprint = hash('sha256', (string) json_encode([
                'pair_count' => count($ratesMap),
                'pairs' => array_keys($ratesMap),
                'fetched_at' => $fetchedAt,
            ]));

            $snapshot = new MarketSnapshot(
                source: MarketSnapshot::SOURCE_BESTCHANGE,
                snapshotId: 'bc_'.$fetchedAt.'_'.substr($fingerprint, 0, 12),
                sourceTimestamp: $fetchedAt,
                fetchedAt: $fetchedAt,
                normalizedAt: $normalizedAt,
                expiresAt: $fetchedAt + FastMarketFlags::expiredThresholdSeconds(),
                fingerprint: $fingerprint,
                ratesByPair: $ratesMap,
                pairByDirectionExchangeId: $pairByDeId,
                bcIdByDirectionExchangeId: $bcIdByDeId,
                externalRates: is_array($externalRates) ? $externalRates : [],
                exchangersMap: $exchangersMap,
                pairCount: count($ratesMap),
                mappedDirectionCount: count($pairByDeId),
                fetchMs: $fetchMs,
                buildMs: (int) floor(microtime(true) * 1000) - $t0,
            );

            $this->store->put($snapshot);

            Log::info('fast_market_snapshot_refreshed', [
                'snapshot_id' => $snapshot->snapshotId,
                'pair_count' => $snapshot->pairCount,
                'mapped_directions' => $snapshot->mappedDirectionCount,
                'fetch_ms' => $snapshot->fetchMs,
                'build_ms' => $snapshot->buildMs,
            ]);

            return ['ok' => true, 'snapshot' => $snapshot, 'error' => null, 'skipped' => false];
        } catch (Throwable $e) {
            Log::error('fast_market_snapshot_refresh_error', ['message' => $e->getMessage()]);

            return ['ok' => false, 'snapshot' => null, 'error' => $e->getMessage(), 'skipped' => false];
        } finally {
            if ($lock !== null) {
                try {
                    $lock->release();
                } catch (Throwable) {
                }
            }
        }
    }

    /**
     * @return Collection<int, BestChangeDirection>
     */
    private function loadActiveDirections(): Collection
    {
        return BestChangeDirection::query()
            ->where('status', 1)
            ->whereHas('direction_exchange', static fn ($q) => $q->where('status', 1))
            ->with([
                'direction_exchange:id,id_currency1,id_currency2',
                'direction_exchange.currency1:id,designation_xml',
                'direction_exchange.currency2:id,designation_xml',
            ])
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, BestChangeDirection>  $directions
     * @return array{0: list<string>, 1: array<int, string>}
     */
    private function buildPairsIndex(Collection $directions): array
    {
        $unique = [];
        $map = [];

        foreach ($directions as $dir) {
            $from = (int) ($dir->id_currency_in ?? 0);
            $to = (int) ($dir->id_currency_out ?? 0);
            $city = (int) ($dir->city_id ?? 0);
            if ($from <= 0 || $to <= 0) {
                continue;
            }
            $pairKey = $this->pairKeyFactory->fromDirection($from, $to, $city)->toString();
            $unique[$pairKey] = true;
            $map[(int) $dir->id] = $pairKey;
        }

        return [array_keys($unique), $map];
    }

    /**
     * @return array<int, string>
     */
    private function buildExchangersMap(): array
    {
        $rows = $this->catalog->exchangers(false);
        $map = [];
        foreach ($rows as $id => $row) {
            $changerId = (int) $id;
            if ($changerId <= 0) {
                continue;
            }
            $map[$changerId] = (string) ($row['name'] ?? '');
        }

        return $map;
    }
}
