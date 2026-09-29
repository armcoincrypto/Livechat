<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Services;

use App\Models\DirectionExchange;
use App\Services\Rates\BestChangeMarketBaseHealth;
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

        $baseQuery->chunkById(500, function ($exchanges) use ($onError): void {
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
                            $bd = \App\Models\BestChangeDirection::query()
                                ->where('id_direction_exchange', $directionId)
                                ->where('status', 1)
                                ->orderByDesc('id')
                                ->first();
                            if ($bd) {
                                $sourcePos = (int) ($bd->position_num ?: 0) ?: null;
                                // Fresh rates handle per direction — do not reuse sortedRows.
                                $market = app(\iEXPackages\BestChange\BestChange::class)
                                    ->rates()
                                    ->marketForDirection($bd);
                                $book = (is_object($market) && isset($market->rawRows) && is_array($market->rawRows))
                                    ? array_values($market->rawRows)
                                    : [];
                                if ($book !== []) {
                                    usort(
                                        $book,
                                        static fn ($a, $b) => bccomp((string) ($a['rate'] ?? '0'), (string) ($b['rate'] ?? '0'), 18)
                                    );
                                }
                                // Reject cross-pair books (presence pair must match BD currencies).
                                $expectedPair = (int) $bd->id_currency_in.'-'.(int) $bd->id_currency_out;
                                $gotPair = is_object($market) && isset($market->presence['pair'])
                                    ? (string) $market->presence['pair']
                                    : $expectedPair;
                                if ($gotPair !== $expectedPair) {
                                    Log::warning('BestChangeExternalTopNController pair_mismatch', [
                                        'direction_exchange_id' => $directionId,
                                        'expected' => $expectedPair,
                                        'got' => $gotPair,
                                    ]);
                                    $book = [];
                                }
                            }
                            $exportDecimals = (int) ($exchange->currency2->number_format ?? 4);
                            // ADMIN_POSITION_CHANGE_DYNAMIC: external target follows position_num.
                            $adj = $topN->adjust(
                                $exchange,
                                $courseValue,
                                $book,
                                max(0, $exportDecimals),
                                $sourcePos,
                                $sourcePos,
                            );
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
                    $successUpdates[] = [
                        'id'                 => $directionId,
                        'course_value'       => $courseValue,
                        'exchange_rate'      => $exchangeRateLabel,
                        'is_error_rate'      => 0,
                        'error_rate_text'    => null,
                        'parser_source_name' => 'BestChange',
                        'updated_at'         => $now,
                    ];
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
                DirectionExchange::upsert(
                    $successUpdates,
                    ['id'],
                    ['course_value', 'exchange_rate', 'is_error_rate', 'error_rate_text', 'parser_source_name', 'updated_at']
                );
            }

            if ($errorUpdates !== []) {
                // Важно: при ошибке НЕ затираем course_value/exchange_rate
                DirectionExchange::upsert(
                    $errorUpdates,
                    ['id'],
                    ['is_error_rate', 'error_rate_text', 'parser_source_name', 'updated_at']
                );
            }
        });
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
