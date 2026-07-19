<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Services;

use App\Models\BestChangeDirection;
use App\Services\AggregatedRatesService;
use App\Services\Rates\BestChangeCurrencyCatalogGuard;
use App\Services\Rates\PeerRateSelector;
use App\Services\Rates\RateSanityGuard;
use App\Settings\BestChangeConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use iEXPackages\BestChange\Contracts\BestChangeHttpClientInterface;
use iEXPackages\BestChange\DTO\ComputedRate;
use iEXPackages\BestChange\DTO\PreparedMarket;
use iEXPackages\BestChange\DTO\RateSelectionPolicy;
use iEXPackages\BestChange\DTO\UpdateResult;

final class RatesUpdateService
{
    private const RELIABILITY_SEEN_LIMIT = 50;

    public function __construct(
        private readonly BestChangeConfig $settings,
        private readonly BestChangeHttpClientInterface $httpClient,
        private readonly BestChangeCatalogRepository $catalog,
        private readonly PairKeyFactory $pairKeyFactory,
        private readonly RateSelectionPolicyResolver $policyResolver,
        private readonly RateCalculator $calculator,
        private readonly AggregatedRatesService $externalRatesService,
        private readonly ParserErrorRecorder $errorRecorder,
        private readonly ExplainBuilder $explainBuilder,
        private readonly BestChangeMarketPipeline $marketPipeline,
        private readonly ExchangerReliabilityService $reliability,
    ) {}

    public function run(): UpdateResult
    {
        if (!$this->settings->isEnabled()) {
            return new UpdateResult(0, 0, 0);
        }

        try {
            // 1) каталоги
            $this->catalog->warmupAll(false);

            // 2) внешние курсы
            $externalRates = $this->externalRatesService->fetch();

            // 3) направления
            $directions = $this->loadActiveDirections();
            $totalDirections = $directions->count();

            // 4) пары
            [$pairKeys, $directionToPairKey] = $this->buildPairsIndex($directions);
            $uniquePairs = count($pairKeys);

            if ($uniquePairs === 0) {
                return new UpdateResult(0, $totalDirections, 0);
            }

            // 5) /rates (одним батчем с чанками до 500 внутри клиента)
            try {
                $ratesMap = $this->httpClient->fetchRatesBatch($pairKeys);
            } catch (\Throwable) {
                // Важно: не затираем курсы ошибкой сети
                return new UpdateResult(0, $totalDirections, $uniquePairs);
            }

            // 6) exchangers map
            $exchangersMap = $this->buildExchangersMap();

            $globalBlacklist = $this->settings->blacklist();
            $defaultPosition = max(1, (int) $this->settings->position());

            $updatedDirections = 0;
            $updates = [];

            foreach ($directions as $direction) {
                $directionId = (int) $direction->id;

                $pairKey = $directionToPairKey[$directionId] ?? null;
                if ($pairKey === null) {
                    // только если реально надо поменять на error
                    if ((int)$direction->is_error_parser !== 1 || (string)$direction->rate_value !== '0') {
                        $updates[] = $this->errorUpdateRow($direction);
                        $updatedDirections++;
                    }
                    continue;
                }

                $rawRows = is_array($ratesMap[$pairKey] ?? null) ? (array) $ratesMap[$pairKey] : [];

                $policy = $this->policyResolver->resolve($direction, $this->settings);

                $computed = $this->computeDirection(
                    direction: $direction,
                    rawRows: $rawRows,
                    policy: $policy,
                    defaultPosition: $defaultPosition,
                    globalBlacklist: $globalBlacklist,
                    externalRates: $externalRates,
                    exchangersMap: $exchangersMap,
                    pairKey: $pairKey,
                );

                if ($computed === null) {
                    if ((int)$direction->is_error_parser !== 1 || (string)$direction->rate_value !== '0') {
                        $updates[] = $this->errorUpdateRow($direction);
                        $updatedDirections++;
                    }
                    continue;
                }

                $newRate   = (string) $computed['computed']->rateValue;
                $newRateWs = (string) $computed['computed']->rateValueWithoutStep;
                $newSource = (string) $computed['computed']->sourceName;
                $newExplain = $computed['explain_json'];

                $isChanged =
                    (string)($direction->rate_value ?? '0') !== $newRate
                    || (string)($direction->rate_value_without_step ?? '0') !== $newRateWs
                    || (string)($direction->source_name ?? '') !== $newSource
                    || (int)($direction->is_error_parser ?? 0) !== 0;

                if (!$isChanged) {
                    // ничего не изменилось — пропускаем запись
                    continue;
                }

                $updates[] = [
                    'id' => $directionId,
                    'rate_value' => $newRate,
                    'rate_value_without_step' => $newRateWs,
                    'source_name' => $newSource,
                    'is_error_parser' => 0,
                    'explain_payload' => $newExplain,
                ];

                $updatedDirections++;
            }

            if ($updates !== []) {
                BestChangeDirection::upsert(
                    $updates,
                    ['id'],
                    ['rate_value', 'rate_value_without_step', 'source_name', 'is_error_parser', 'explain_payload']
                );
            }

            return new UpdateResult($updatedDirections, $totalDirections, $uniquePairs);

        } finally {
            // batched reliability writes
            $this->reliability->flush();
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
            ->orderBy('id')
            ->get();
    }

    /**
     * @param Collection<int, BestChangeDirection> $directions
     * @return array{0:string[],1:array<int,string>}
     */
    private function buildPairsIndex(Collection $directions): array
    {
        $unique = [];
        $map = [];

        foreach ($directions as $dir) {
            $from = (int) ($dir->id_currency_in ?? 0);
            $to   = (int) ($dir->id_currency_out ?? 0);
            $city = (int) ($dir->city_id ?? 0);

            if ($from <= 0 || $to <= 0) {
                continue;
            }

            $pairKey = $this->pairKeyFactory->fromDirection($from, $to, $city)->toString();

            $unique[$pairKey] = true;
            $map[(int)$dir->id] = $pairKey;
        }

        return [array_keys($unique), $map];
    }

    /**
     * @return array<int,string>
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

    /**
     * @param array<int,array<string,mixed>> $rawRows
     * @param array<int,mixed> $globalBlacklist
     * @param array<string,string> $externalRates
     * @param array<int,string> $exchangersMap
     * @return array{computed:ComputedRate, explain_json:string}|null
     */
    private function computeDirection(
        BestChangeDirection $direction,
        array $rawRows,
        RateSelectionPolicy $policy,
        int $defaultPosition,
        array $globalBlacklist,
        array $externalRates,
        array $exchangersMap,
        string $pairKey,
    ): ?array {
        $explain = $this->explainBuilder->fresh();
        $market = null;

        try {
            $explain->setBaseContext($pairKey, $policy);

            $market = $this->marketPipeline->prepare(
                $direction,
                $rawRows,
                $policy,
                $defaultPosition,
                $globalBlacklist,
                $pairKey,
                false,
                0,
            );

            $explain->setPresence(null);
            $explain->setMarketStats(count($market->rawRows), count($market->filteredRows));
            $explain->setRejectedCounters($market->rejectCounters);

            if ($market->filteredRows === []) {
                throw new \RuntimeException('после фильтрации 0 строк');
            }
            if ($market->selectedRow === null) {
                throw new \RuntimeException('не удалось выбрать строку');
            }

            $this->assertBestChangeCurrencyMapping($direction);

            $this->touchReliabilitySeen($market);

            $computed = $this->calculator->calculate(
                direction: $direction,
                selectedRow: $market->selectedRow,
                sortedRows: $market->sortedRows,
                policy: $policy,
                externalRates: $externalRates,
                exchangersMap: $exchangersMap
            );

            if ($computed === null) {
                throw new \RuntimeException('не удалось вычислить курс');
            }

            $computed = $this->applyRateSanityGuard(
                direction: $direction,
                computed: $computed,
                market: $market,
                policy: $policy,
            );

            if ($computed === null) {
                throw new \RuntimeException('курс отклонён RateSanityGuard');
            }

            $this->touchReliabilitySelected($market);

            $selectedRow = $market->selectedRow;
            $qualityScore = is_scalar($selectedRow['_quality_score'] ?? null) ? (int) $selectedRow['_quality_score'] : null;
            $qualityReasons = is_array($selectedRow['_quality_reasons'] ?? null) ? $selectedRow['_quality_reasons'] : null;

            $explain->setSelection(
                selectedRow: $selectedRow,
                sourceName: $computed->sourceName,
                qualityScore: $qualityScore,
                qualityReasons: $qualityReasons
            );

            return [
                'computed' => $computed,
                'explain_json' => $explain->toJson(),
            ];

        } catch (\Throwable $e) {
            $cid = 0;
            if ($market instanceof PreparedMarket && is_array($market->selectedRow)) {
                $cid = $this->readChangerId($market->selectedRow);
            }
            if ($cid > 0) {
                $this->reliability->error($cid);
            }

            $this->recordParserErrorIfNeeded($direction, $policy, $pairKey, $e);
            return null;
        }
    }

    private function touchReliabilitySeen(PreparedMarket $market): void
    {
        $seen = 0;

        foreach ($market->filteredRows as $row) {
            if ($seen >= self::RELIABILITY_SEEN_LIMIT) {
                break;
            }

            $cid = $this->readChangerId(is_array($row) ? $row : null);
            if ($cid > 0) {
                $this->reliability->seen($cid);
                $seen++;
            }
        }
    }

    private function touchReliabilitySelected(PreparedMarket $market): void
    {
        if (!is_array($market->selectedRow)) {
            return;
        }

        $cid = $this->readChangerId($market->selectedRow);
        if ($cid <= 0) {
            return;
        }

        $score = is_scalar($market->selectedRow['_quality_score'] ?? null) ? (int) $market->selectedRow['_quality_score'] : 0;
        $this->reliability->selected($cid, $score);
    }

    /**
     * Reject BestChange currency IDs that no longer match the live catalog
     * for the currency code claimed by this direction (e.g. PRUSD id 108 → CARDVND).
     */
    private function assertBestChangeCurrencyMapping(BestChangeDirection $direction): void
    {
        $guard = BestChangeCurrencyCatalogGuard::fromStorageApp();
        $name = (string) ($direction->name ?? '');
        $expectedOut = null;
        if (preg_match('/->\s*\[([A-Za-z0-9]+)\]/', $name, $m) === 1) {
            $expectedOut = strtoupper($m[1]);
        }
        $expectedIn = null;
        if (preg_match('/^\s*\[([A-Za-z0-9]+)\]/', $name, $m) === 1) {
            $expectedIn = strtoupper($m[1]);
        }

        $checks = [
            'id_currency_in' => $expectedIn,
            'id_currency_out' => $expectedOut,
        ];
        foreach ($checks as $field => $expectedCode) {
            $id = (int) ($direction->{$field} ?? 0);
            if ($id <= 0 || $expectedCode === null || $expectedCode === '') {
                continue;
            }
            $check = $guard->validateId($id, $expectedCode);
            if (($check['ok'] ?? false) === true) {
                continue;
            }
            if (($check['reason'] ?? '') === 'currency_mapping_mismatch') {
                Log::error('BestChange currency catalog mismatch — refusing rate update', [
                    'bestchange_direction_id' => $direction->id ?? null,
                    'direction_exchange_id' => $direction->id_direction_exchange ?? null,
                    'field' => $field,
                    'currency_id' => $id,
                    'expected_code' => $expectedCode,
                    'catalog_name' => $check['catalog_name'] ?? null,
                    'code' => $check['code'] ?? null,
                    'code_name' => $check['code_name'] ?? null,
                ]);
                throw new \RuntimeException('bestchange_currency_mapping_mismatch:' . $expectedCode . ':' . $id);
            }
        }
    }

    /**
     * Clamp or reject absurd BestChange-derived rates vs peer median.
     * Prevents publishing ~spot×1.20 outliers that get BestChange-hidden.
     */
    private function applyRateSanityGuard(
        BestChangeDirection $direction,
        ComputedRate $computed,
        PreparedMarket $market,
        RateSelectionPolicy $policy,
    ): ?ComputedRate {
        $guard = new RateSanityGuard();
        $peers = $guard->peerRatesFromBestChangeRows(
            $market->filteredRows !== [] ? $market->filteredRows : $market->sortedRows,
            $policy->typeField
        );

        $maxDeviation = (string) (iEXSetting('rate_sanity_max_deviation_fraction', RateSanityGuard::DEFAULT_MAX_DEVIATION_FRACTION)
            ?: RateSanityGuard::DEFAULT_MAX_DEVIATION_FRACTION);

        $selector = new PeerRateSelector($guard);
        $selection = $selector->selectValidPeerRate(
            peerRates: $peers,
            preferred: $computed->rateValue,
            maxMedianDeviation: $maxDeviation,
            minSample: 3,
        );

        if (!$selection['ok'] && ($selection['reason'] ?? '') === 'outlier_peer_rejected') {
            Log::warning('PeerRateSelector rejected outlier preferred peer', [
                'bestchange_direction_id' => $direction->id ?? null,
                'direction_exchange_id' => $direction->id_direction_exchange ?? null,
                'selection' => $selection,
            ]);
            // Fall through to median clamp via RateSanityGuard rather than publishing outlier.
        }

        if (!$selection['ok'] && ($selection['reason'] ?? '') === 'insufficient_peer_sample') {
            Log::info('PeerRateSelector insufficient sample — continuing with RateSanityGuard only', [
                'bestchange_direction_id' => $direction->id ?? null,
                'sample_size' => $selection['sample_size'],
            ]);
        }

        $eval = $guard->evaluate(
            candidate: $computed->rateValue,
            peerRates: $peers,
            maxDeviationFraction: $maxDeviation,
            clamp: true
        );

        if ($eval['action'] === 'pass') {
            return $computed;
        }

        Log::warning('RateSanityGuard adjusted BestChange rate', [
            'bestchange_direction_id' => $direction->id ?? null,
            'direction_exchange_id' => $direction->id_direction_exchange ?? null,
            'action' => $eval['action'],
            'reason' => $eval['reason'],
            'candidate' => $eval['candidate'],
            'baseline' => $eval['baseline'],
            'ratio' => $eval['ratio'],
            'effective' => $eval['effective'],
            'peer_count' => count($peers),
            'peer_selection' => $selection,
        ]);

        if ($eval['action'] === 'reject' || !$eval['ok']) {
            return null;
        }

        return new ComputedRate(
            rateValue: $eval['effective'],
            rateValueWithoutStep: $computed->rateValueWithoutStep,
            sourceName: $computed->sourceName . '+sanity',
        );
    }

    private function readChangerId(?array $row): int
    {
        if (!is_array($row)) {
            return 0;
        }

        $v = $row['changer'] ?? 0;
        return is_numeric($v) ? (int)$v : 0;
    }

    private function errorUpdateRow(BestChangeDirection $direction): array
    {
        return [
            'id' => (int) $direction->id,
            'rate_value' => '0',
            'rate_value_without_step' => '0',
            'source_name' => 'None',
            'is_error_parser' => 1,
            'explain_payload' => null,
        ];
    }

    private function recordParserErrorIfNeeded(
        BestChangeDirection $direction,
        RateSelectionPolicy $policy,
        string $pairKey,
        \Throwable $e
    ): void {
        $enabled = $this->settings->isLogError() || (int) iEXSetting('is_enable_bs_log_error') === 1;
        if (!$enabled) {
            return;
        }

        $mode = (string) ($direction->rate_mode ?? $this->settings->rateMode());

        $this->errorRecorder->record(
            bestChangeId: (int) $direction->id,
            directionExchangeId: (int) $direction->id_direction_exchange,
            status: 1,
            description: sprintf(
                'pair=%s; type=%s; mode=%s; reason=%s',
                $pairKey,
                $policy->typeField,
                $mode,
                $e->getMessage()
            )
        );
    }
}
