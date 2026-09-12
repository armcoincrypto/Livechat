<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Services;

use App\Models\DirectionExchange;
use iEXPackages\Calculator\CalculatorFacade;
use Illuminate\Support\Carbon;
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

                    $health = \App\Services\Rates\BestChangeMarketBaseHealth::evaluate($directionId);
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
                                    $courseValue = (string) $adj['course'];
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
}
