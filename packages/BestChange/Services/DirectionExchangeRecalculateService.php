<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Services;

use App\Models\DirectionExchange;
use App\Services\Rates\BestChangeCompilerGenerationContext;
use App\Services\Rates\BestChangeL1Authority;
use App\Services\Rates\BestChangeL1SameSnapshotDiagnostic;
use App\Services\Rates\BestChangeL1Shadow;
use App\Services\Rates\BestChangeMarketBaseHealth;
use App\Services\Rates\BestChangeTopNBookHelper;
use iEXPackages\Calculator\CalculatorFacade;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * DirectionExchangeRecalculateService
 *
 * Пересчитывает таблицу direction_exchange после обновления bestchange_directions.
 *
 * Важно по производительности:
 * - Убираем N+1 на профилях городов: калькулятор использует $city->profile (hasOneThrough),
 *   поэтому обязательно eager-load direction_exchange_cities.profile.
 * - Загружаем обе системы “комиссий выбора” (если калькулятор использует обе):
 *   - selectorFees (belongsToMany SelectorFee)
 *   - direction_exchange_selector_fees (hasMany DirectionExchangeSelectorFee)
 * - Class-A2 (2026-09-29): per chunkById batch, preload bestchange_directions once
 *   (status DESC, id ASC — same preference as BestChangeMarketBaseHealth::evaluate)
 *   and evaluateFromRow(..., withDerivedOutlier: true). Does NOT cache marketForDirection.
 */
final class DirectionExchangeRecalculateService
{
    /**
     * Выполнить пересчёт всех направлений, которые используют BestChange.
     *
     * @param callable(DirectionExchange,\Throwable):void|null $onError
     */
    public function run(?callable $onError = null): void
    {
        $recalcStartedMs = (int) floor(microtime(true) * 1000);
        $metrics = [
            'bc_direction_calc_count' => 0,
            'bc_topn_lookup_count' => 0,
            'bc_topn_network_fetch_count' => 0,
            'bc_topn_book_reuse_count' => 0,
            'bc_l1_authority_enabled' => 0,
            'bc_l1_authority_fallback_count' => 0,
            'bc_topn_shadow_compare_count' => 0,
            'bc_topn_shadow_match' => 0,
            'bc_topn_shadow_mismatch' => 0,
            'bc_direction_upsert_count' => 0,
            'bc_direction_upsert_skipped_identical' => 0,
            'bc_shadow_production_writes' => 0,
            'bc_same_snapshot_compare_count' => 0,
            'bc_same_snapshot_match' => 0,
            'bc_same_snapshot_mismatch' => 0,
            'bc_same_snapshot_diagnostic_errors' => 0,
            'bc_same_snapshot_extra_network_fetches' => 0,
            'bc_same_snapshot_production_writes' => 0,
        ];
        $shadowMismatchClasses = [];
        // Rotates sample cohort ~every 10 minutes across natural BC generations.
        $sameSnapshotGenerationSalt = (int) floor(time() / 600);

        $zelleId = \App\Services\Rates\ZelleOutgoingRateWriteGuard::zelleCurrencyId();
        $baseQuery = DirectionExchange::query()
            ->where('status', 1)
            // ZELLEUSD outgoing: include only when BestChange is intentional runtime owner.
            // Legacy Zelle-benchmark rows remain excluded (prevents dual-owner / circularity).
            ->where(static function ($q) use ($zelleId): void {
                $q->where('id_currency1', '!=', $zelleId)
                    ->orWhere(static function ($q2) use ($zelleId): void {
                        $q2->where('id_currency1', $zelleId)
                            ->where('parser_source_name', 'BestChange');
                    });
            })
            ->whereHas('bestchange_directions', static fn ($q) => $q->where('status', 1))
            ->with([

                // designation_xml is required for BestChangeExternalTopNController::classifyFamily
                // (limited selects without it make every family null → Top-N silently no-ops).
                'currency1:id,number_format,id_payment,id_code_currency,designation_xml',
                'currency1.payment:id,name',
                'currency1.code_currency:id,name,sign',

                'currency2:id,number_format,id_payment,id_code_currency,designation_xml',
                'currency2.payment:id,name',
                'currency2.code_currency:id,name,sign',

                'direction_exchange_cities.profile:direction_city_profiles.id,direction_city_profiles.name,direction_city_profiles.add_comm,direction_city_profiles.profit,direction_city_profiles.profit_s',
                'direction_exchange_cities.cityProfiles:direction_city_profiles.id,direction_city_profiles.name,direction_city_profiles.add_comm,direction_city_profiles.profit,direction_city_profiles.profit_s',

                'groupCommissions:id,receiving',

                'bestchange_directions' => static fn ($q) => $q->where('status', 1),

                'bestchange_directions.new_default_parser:id,summa,status',
                'bestchange_directions.new_formula_parser:id,summa,status',

                'direction_exchange_percentage_amount:id,id_direction_exchange,percentage,from_amount,to_amount',

                'selectorFees:id,name,description,fee', // belongsToMany SelectorFee
                'direction_exchange_selector_fees:id,id_direction_exchange,fee,name,description', // hasMany DirectionExchangeSelectorFee

                // --- Parsers / file parsers / formula parsers
                'parser_exchange:id,id_group,value,summa,status',
                'parser_exchange.group_parse_exchange:id,name',

                'file_parser_rates:id,id_group,value,summa,number_format,status',
                'file_parser_rates.file_parser_group:id,name',

                'parser_formula_rates:id,value,summa,number_format,status',
            ])
            ->orderBy('id');

        $generationContext = app(BestChangeCompilerGenerationContext::class);
        $shadowEnabled = BestChangeL1Shadow::shadowEnabled();
        $skipIdentical = BestChangeL1Shadow::skipIdenticalDeUpsertEnabled();
        $sameSnapshotEnabled = BestChangeL1SameSnapshotDiagnostic::enabled();
        $l1AuthorityEnabled = BestChangeL1Authority::enabled();
        $metrics['bc_l1_authority_enabled'] = $l1AuthorityEnabled ? 1 : 0;
        // Observability only: detect shared=.env true vs process-effective false
        // (release-local .env divergence). Never changes pricing writes.
        BestChangeL1SameSnapshotDiagnostic::assertAndLogFlagState();

        $baseQuery->chunkById(500, function ($exchanges) use ($onError, &$metrics, &$shadowMismatchClasses, $generationContext, $shadowEnabled, $skipIdentical, $sameSnapshotEnabled, $sameSnapshotGenerationSalt, $l1AuthorityEnabled): void {
            $now = Carbon::now()->toDateTimeString();

            $successUpdates = [];
            $errorUpdates = [];

            // A2e: one SELECT for the chunk instead of per-direction evaluate() SELECTs.
            // Row preference matches BestChangeMarketBaseHealth::evaluate (status DESC, id ASC).
            $bcByDirectionId = [];
            $chunkIds = [];
            foreach ($exchanges as $exchange) {
                $chunkIds[] = (int) $exchange->id;
            }
            if ($chunkIds !== []) {
                $bcRows = DB::table('bestchange_directions')
                    ->whereIn('id_direction_exchange', $chunkIds)
                    ->orderByDesc('status')
                    ->orderBy('id')
                    ->get();
                foreach ($bcRows as $bcRow) {
                    $dirId = (int) ($bcRow->id_direction_exchange ?? 0);
                    if ($dirId > 0 && ! isset($bcByDirectionId[$dirId])) {
                        $bcByDirectionId[$dirId] = $bcRow;
                    }
                }
            }

            foreach ($exchanges as $exchange) {
                $metrics['bc_direction_calc_count']++;
                try {
                    $directionId = (int) $exchange->id;

                    // Fail-closed: derived market BASE with block_bestchange_overwrite
                    // must never be rewritten by BestChange recalculate, even if an
                    // admin/link race re-enabled bestchange_directions.status=1.
                    $protected = \App\Services\Rates\ProtectedMarketBaselineWriteGuard::denyBestchangeWrite(
                        $directionId,
                        'DirectionExchangeRecalculateService'
                    );
                    if ($protected['blocked']) {
                        continue;
                    }

                    if (isset($bcByDirectionId[$directionId])) {
                        $health = BestChangeMarketBaseHealth::evaluateFromRow(
                            $bcByDirectionId[$directionId],
                            $directionId,
                            null,
                            withDerivedOutlier: true
                        );
                    } else {
                        $health = BestChangeMarketBaseHealth::evaluate($directionId);
                    }
                    if (!$health['healthy']) {
                        continue;
                    }

                    $derivedOwned = \App\Services\Rates\DerivedMarketBaselineAuthority::fromStorageApp()
                        ->owns($directionId);

                    if ($derivedOwned) {
                        // Raw competitor BASE; Canonical applies admin Прибыль.
                        $courseValue = (string) ($health['rate_value'] ?? '0');
                        $exchangeRateLabel = $courseValue;
                        try {
                            $labelDir = clone $exchange;
                            $labelDir->course_value = $courseValue;
                            $labelDir->manual_rate_value = $courseValue;
                            $labelDir->parser_source_name = 'BestChange';
                            $exchangeRateLabel = \App\Services\Rates\AdminRateSourcePresenter::liveExchangeRateLabel($labelDir);
                        } catch (\Throwable) {
                            $exchangeRateLabel = $courseValue;
                        }
                    } else {
                        $calculator = CalculatorFacade::setDirectionExchange($exchange)->withoutOptions()->calculate();
                        $courseValue = (string) $calculator->getRateValue();
                        $exchangeRateLabel = (string) $calculator->getFullRate();
                    }

                    // Post-profit BestChange courses must not be raised by Top-N.
                    // Derived raw-base storage and Zelle pre-profit authority are excluded.
                    $clampTopNToCalculator = self::topNProfitFloorApplies($exchange, $derivedOwned);

                    // Never wipe a healthy manual/baseline course when BestChange
                    // is enabled but yields no positive market rate.
                    if (!is_numeric($courseValue) || (float) $courseValue <= 0) {
                        $errorUpdates[] = [
                            'id'                 => $directionId,
                            'is_error_rate'      => 1,
                            'error_rate_text'    => 'bestchange_recalculate_non_positive_rate',
                            'parser_source_name' => 'BestChange',
                            'updated_at'         => $now,
                        ];
                        continue;
                    }

                    // Model B: generic BestChange external Top-N controller (canonical course).
                    // Eligible families only (currently Coin→Zelle certified). Target = position_num.
                    try {
                        $topN = app(\App\Services\Rates\BestChangeExternalTopNController::class);
                        if ($topN->isEligible($exchange)) {
                            $book = [];
                            $sourcePos = null;
                            $adj = null;
                            $bd = \App\Models\BestChangeDirection::query()
                                ->where('id_direction_exchange', $directionId)
                                ->where('status', 1)
                                ->orderByDesc('id')
                                ->first();
                            if ($bd) {
                                $sourcePos = (int) ($bd->position_num ?: 0) ?: null;
                                $metrics['bc_topn_lookup_count']++;
                                // Authoritative book source:
                                // - BESTCHANGE_L1_AUTHORITY_ENABLED=true → prefer generationContext
                                //   snapshot from RatesUpdateService::fetchRatesBatch (no duplicate
                                //   network fetch). Fail-soft to marketForDirection when unavailable.
                                // - flag false (default) → historical marketForDirection path.
                                // Same Top-N policy / adjust() / calculator — no second engine.
                                $expectedPair = (int) $bd->id_currency_in.'-'.(int) $bd->id_currency_out;
                                $batchRaw = $generationContext->rawRowsForDirectionExchange($directionId);
                                $market = null;
                                $useL1Book = $l1AuthorityEnabled && is_array($batchRaw) && $batchRaw !== [];
                                if ($useL1Book) {
                                    $book = BestChangeTopNBookHelper::normalizeForTopN($batchRaw);
                                    $metrics['bc_topn_book_reuse_count']++;
                                    $gotPair = $expectedPair;
                                } else {
                                    if ($l1AuthorityEnabled) {
                                        $metrics['bc_l1_authority_fallback_count']++;
                                    }
                                    $market = app(\iEXPackages\BestChange\BestChange::class)
                                        ->rates()
                                        ->marketForDirection($bd);
                                    $metrics['bc_topn_network_fetch_count']++;
                                    $book = (is_object($market) && isset($market->rawRows) && is_array($market->rawRows))
                                        ? array_values($market->rawRows)
                                        : [];
                                    if ($book !== []) {
                                        $book = BestChangeTopNBookHelper::normalizeForTopN($book);
                                    }
                                    $gotPair = is_object($market) && isset($market->presence['pair'])
                                        ? (string) $market->presence['pair']
                                        : $expectedPair;
                                }
                                // Reject cross-pair books (presence pair must match BD currencies).
                                if ($gotPair !== $expectedPair) {
                                    Log::warning('BestChangeExternalTopNController pair_mismatch', [
                                        'direction_exchange_id' => $directionId,
                                        'expected' => $expectedPair,
                                        'got' => $gotPair,
                                    ]);
                                    $book = [];
                                }

                                // L1 shadow: reuse generation batch book; compare; discard.
                                if ($shadowEnabled) {
                                    $batchRaw = $generationContext->rawRowsForDirectionExchange($directionId);
                                    if (is_array($batchRaw)) {
                                        $shadowBook = BestChangeTopNBookHelper::normalizeForTopN($batchRaw);
                                        $exportDecimalsShadow = (int) ($exchange->currency2->number_format ?? 4);
                                        $shadowAdj = $topN->adjust(
                                            $exchange,
                                            $courseValue,
                                            $shadowBook,
                                            max(0, $exportDecimalsShadow),
                                            $sourcePos,
                                            $sourcePos,
                                        );
                                        $authAdjPreview = $topN->adjust(
                                            $exchange,
                                            $courseValue,
                                            $book,
                                            max(0, $exportDecimalsShadow),
                                            $sourcePos,
                                            $sourcePos,
                                        );
                                        $adj = $authAdjPreview;
                                        $authCourseShadow = (string) ($authAdjPreview['course'] ?? $courseValue);
                                        $shadowCourse = (string) ($shadowAdj['course'] ?? $courseValue);
                                        $booksEqual = BestChangeTopNBookHelper::topNRelevantEqual($book, $shadowBook);
                                        $classified = BestChangeL1Shadow::classifyTopNMismatch(
                                            $booksEqual,
                                            $authCourseShadow,
                                            $shadowCourse,
                                            $authAdjPreview,
                                            $shadowAdj,
                                        );
                                        $metrics['bc_topn_shadow_compare_count']++;
                                        if ($classified['match']) {
                                            $metrics['bc_topn_shadow_match']++;
                                        } else {
                                            $metrics['bc_topn_shadow_mismatch']++;
                                            $cls = (string) $classified['class'];
                                            $shadowMismatchClasses[$cls] = ($shadowMismatchClasses[$cls] ?? 0) + 1;
                                            if (($shadowMismatchClasses[$cls] ?? 0) <= 3) {
                                                Log::warning('bestchange_l1_shadow_mismatch', [
                                                    'direction_exchange_id' => $directionId,
                                                    'class' => $cls,
                                                    'books_equal' => $booksEqual,
                                                    'auth_course' => $authCourseShadow,
                                                    'shadow_course' => $shadowCourse,
                                                    'auth_reason' => $authAdjPreview['reason'] ?? null,
                                                    'shadow_reason' => $shadowAdj['reason'] ?? null,
                                                ]);
                                            }
                                        }
                                        // Shadow never writes.
                                        $metrics['bc_shadow_production_writes'] += 0;
                                    }
                                }

                                // Same-snapshot diagnostic: AUTHORITATIVE(book X) vs L1(book X).
                                // Uses the authoritative normalized book only — no second fetch.
                                // Never mutates $adj / customer course / persistence.
                                if ($sameSnapshotEnabled) {
                                    $exportDecimalsDiag = (int) ($exchange->currency2->number_format ?? 4);
                                    BestChangeL1SameSnapshotDiagnostic::runFailSoft(
                                        $topN,
                                        $exchange,
                                        $courseValue,
                                        $book,
                                        max(0, $exportDecimalsDiag),
                                        $sourcePos,
                                        $metrics,
                                        $sameSnapshotGenerationSalt,
                                    );
                                }
                            }
                            $exportDecimals = (int) ($exchange->currency2->number_format ?? 4);
                            // ADMIN_POSITION_CHANGE_DYNAMIC: external target follows position_num.
                            // Prefer already-computed auth preview from shadow block when present.
                            if (! isset($adj) || ! is_array($adj)) {
                                $adj = $topN->adjust(
                                    $exchange,
                                    $courseValue,
                                    $book,
                                    max(0, $exportDecimals),
                                    $sourcePos,
                                    $sourcePos,
                                );
                            }
                            // Hard sanity: never jump course by >2% vs BASE in one step.
                            if (!empty($adj['applied']) && is_numeric($adj['course'] ?? null) && (float) $adj['course'] > 0) {
                                $ratio = bcdiv((string) $adj['course'], $courseValue, 18);
                                $jump = abs((float) bcmul(bcsub($ratio, '1', 18), '100', 8));
                                if ($jump > 2.0) {
                                    Log::warning('BestChangeExternalTopNController sanity_block', [
                                        'direction_exchange_id' => $directionId,
                                        'base' => $courseValue,
                                        'target' => $adj['course'],
                                        'jump_pct' => $jump,
                                    ]);
                                } else {
                                    $proposed = (string) $adj['course'];
                                    $persisted = self::persistedCourseAfterTopN(
                                        $courseValue,
                                        $proposed,
                                        $clampTopNToCalculator
                                    );
                                    if (self::courseStrictlyGreater($proposed, $courseValue) && $clampTopNToCalculator) {
                                        // Keep the Calculator course. Do not record a Top-N write.
                                        Log::warning('BestChangeExternalTopNController profit_floor_blocked', [
                                            'direction_exchange_id' => $directionId,
                                            'calculator' => $courseValue,
                                            'topn' => $proposed,
                                        ]);
                                    } else {
                                        $courseValue = $persisted;
                                        try {
                                            $labelDir = clone $exchange;
                                            $labelDir->course_value = $courseValue;
                                            $exchangeRateLabel = \App\Services\Rates\AdminRateSourcePresenter::liveExchangeRateLabel($labelDir);
                                        } catch (\Throwable) {
                                            // keep prior label
                                        }
                                        \App\Services\Rates\RateWriteAuditLogger::record([
                                            'event' => 'bestchange_external_topn',
                                            'direction_id' => $directionId,
                                            'new_base' => $courseValue,
                                            'writer' => 'BestChangeExternalTopNController',
                                            'source' => 'BestChange',
                                            'reason' => (string) ($adj['reason'] ?? 'applied'),
                                            'meta' => $adj['explain'] ?? [],
                                        ]);
                                    }
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::warning('BestChangeExternalTopNController skipped', [
                            'direction_exchange_id' => $directionId,
                            'message' => $e->getMessage(),
                        ]);
                    }

                    $oldBase = (string) ($exchange->course_value ?? '');
                    $newRow = [
                        'id'                 => $directionId,
                        'course_value'       => $courseValue,
                        'exchange_rate'      => $exchangeRateLabel,
                        'is_error_rate'      => 0,
                        'error_rate_text'    => null,
                        'parser_source_name' => 'BestChange',
                        'updated_at'         => $now,
                    ];
                    if ($skipIdentical && self::deSemanticFieldsIdentical($exchange, $newRow)) {
                        $metrics['bc_direction_upsert_skipped_identical']++;
                    } else {
                        $successUpdates[] = $newRow;
                    }
                    if ($oldBase !== $courseValue) {
                        \App\Services\Rates\RateWriteAuditLogger::record([
                            'event' => 'base_change',
                            'direction_id' => $directionId,
                            'old_base' => $oldBase,
                            'new_base' => $courseValue,
                            'writer' => 'DirectionExchangeRecalculateService',
                            'source' => 'BestChange',
                            'reason' => $derivedOwned
                                ? 'bestchange_recalculate_raw_base'
                                : 'bestchange_recalculate',
                            'position' => $health['position_num'] ?? null,
                        ]);
                    }
                } catch (\Throwable $e) {
                    $errorUpdates[] = [
                        'id'                 => (int) $exchange->id,
                        'is_error_rate'      => 1,
                        'error_rate_text'    => $e->getMessage(),
                        'parser_source_name' => 'BestChange',
                        'updated_at'         => $now,
                    ];

                    if ($onError) {
                        $onError($exchange, $e);
                    } else {
                        Log::error('DirectionExchangeRecalculateService error', [
                            'direction_exchange_id' => (int) $exchange->id,
                            'tech_name' => $exchange->tech_name ?? null,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }
            }

            if ($successUpdates !== []) {
                $metrics['bc_direction_upsert_count'] += count($successUpdates);
                DirectionExchange::upsert(
                    $successUpdates,
                    ['id'],
                    ['course_value', 'exchange_rate', 'is_error_rate', 'error_rate_text', 'parser_source_name', 'updated_at']
                );
            }

            if ($errorUpdates !== []) {
                // Важно: при ошибке НЕ затираем course_value/exchange_rate
                $metrics['bc_direction_upsert_count'] += count($errorUpdates);
                DirectionExchange::upsert(
                    $errorUpdates,
                    ['id'],
                    ['is_error_rate', 'error_rate_text', 'parser_source_name', 'updated_at']
                );
            }
        });

        BestChangeL1Shadow::logGenerationMetrics([
            'stage' => 'bc_recalc_complete',
            'bc_recalc_ms' => (int) floor(microtime(true) * 1000) - $recalcStartedMs,
            'bc_topn_lookup_count' => $metrics['bc_topn_lookup_count'],
            'bc_topn_network_fetch_count' => $metrics['bc_topn_network_fetch_count'],
            'bc_topn_book_reuse_count' => $metrics['bc_topn_book_reuse_count'],
            'bc_l1_authority_enabled' => $metrics['bc_l1_authority_enabled'],
            'bc_l1_authority_fallback_count' => $metrics['bc_l1_authority_fallback_count'],
            'bc_direction_calc_count' => $metrics['bc_direction_calc_count'],
            'bc_direction_upsert_count' => $metrics['bc_direction_upsert_count'],
            'bc_direction_upsert_skipped_identical' => $metrics['bc_direction_upsert_skipped_identical'],
            'bc_topn_shadow_compare_count' => $metrics['bc_topn_shadow_compare_count'],
            'bc_topn_shadow_match' => $metrics['bc_topn_shadow_match'],
            'bc_topn_shadow_mismatch' => $metrics['bc_topn_shadow_mismatch'],
            'bc_shadow_production_writes' => $metrics['bc_shadow_production_writes'],
            'shadow_mismatch_classes' => $shadowMismatchClasses,
            'shadow_enabled' => $shadowEnabled ? 1 : 0,
            'skip_identical_upsert' => $skipIdentical ? 1 : 0,
            'l1_authority_enabled' => $l1AuthorityEnabled ? 1 : 0,
            'same_snapshot_diagnostic_enabled' => $sameSnapshotEnabled ? 1 : 0,
            'bc_same_snapshot_compare_count' => $metrics['bc_same_snapshot_compare_count'],
            'bc_same_snapshot_match' => $metrics['bc_same_snapshot_match'],
            'bc_same_snapshot_mismatch' => $metrics['bc_same_snapshot_mismatch'],
            'bc_same_snapshot_diagnostic_errors' => $metrics['bc_same_snapshot_diagnostic_errors'],
            'bc_same_snapshot_extra_network_fetches' => $metrics['bc_same_snapshot_extra_network_fetches'],
            'bc_same_snapshot_production_writes' => $metrics['bc_same_snapshot_production_writes'],
            'generation_pairs' => $generationContext->pairCount(),
        ]);
    }

    /**
     * Semantic DE fields for identical-write skip (excludes heartbeat updated_at).
     *
     * @param  array<string, mixed>  $newRow
     */
    public static function deSemanticFieldsIdentical(DirectionExchange $exchange, array $newRow): bool
    {
        $oldCourse = (string) ($exchange->course_value ?? '');
        $oldLabel = (string) ($exchange->exchange_rate ?? '');
        $oldErr = (int) ($exchange->is_error_rate ?? 0);
        $oldErrText = $exchange->error_rate_text;
        $oldParser = (string) ($exchange->parser_source_name ?? '');

        $newCourse = (string) ($newRow['course_value'] ?? '');
        $newLabel = (string) ($newRow['exchange_rate'] ?? '');
        $newErr = (int) ($newRow['is_error_rate'] ?? 0);
        $newErrText = $newRow['error_rate_text'] ?? null;
        $newParser = (string) ($newRow['parser_source_name'] ?? '');

        $oldErrNorm = ($oldErrText === null || $oldErrText === '') ? null : (string) $oldErrText;
        $newErrNorm = ($newErrText === null || $newErrText === '') ? null : (string) $newErrText;

        return $oldCourse === $newCourse
            && $oldLabel === $newLabel
            && $oldErr === $newErr
            && $oldErrNorm === $newErrNorm
            && $oldParser === $newParser;
    }

    /**
     * True only for the BestChange post-profit path.
     * Derived raw-base storage and Zelle pre-profit Top-N stay on their current writers.
     */
    public static function topNProfitFloorApplies(DirectionExchange $direction, bool $derivedOwned): bool
    {
        if ($derivedOwned) {
            return false;
        }

        $owner = trim((string) ($direction->parser_source_name ?? ''));
        if ($owner !== 'BestChange') {
            return false;
        }

        $from = strtoupper(trim((string) ($direction->currency1->designation_xml ?? '')));
        $to = strtoupper(trim((string) ($direction->currency2->designation_xml ?? '')));
        if ($from === 'ZELLEUSD' || $to === 'ZELLEUSD') {
            return false;
        }

        return true;
    }

    /**
     * Decimal-safe persist rule. When the floor is on, a Top-N course above
     * the Calculator course is discarded. Lower or equal Top-N results are kept.
     * When the floor is off, the Top-N course is returned unchanged.
     */
    public static function persistedCourseAfterTopN(
        string $calculatorCourse,
        string $topnCourse,
        bool $clampToCalculator
    ): string {
        if (!$clampToCalculator) {
            return $topnCourse;
        }
        if (self::courseStrictlyGreater($topnCourse, $calculatorCourse)) {
            return $calculatorCourse;
        }

        return $topnCourse;
    }

    public static function courseStrictlyGreater(string $left, string $right): bool
    {
        if (!is_numeric($left) || !is_numeric($right)) {
            return false;
        }

        return bccomp(bcadd($left, '0', 18), bcadd($right, '0', 18), 18) === 1;
    }
}
