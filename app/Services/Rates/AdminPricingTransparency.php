<?php

declare(strict_types=1);

namespace App\Services\Rates;

use App\Models\BestChangeDirection;
use App\Models\DirectionExchange;
use App\Settings\BestChangeConfig;
use Illuminate\Support\Facades\DB;

/**
 * Read-only owner-facing pricing explanation.
 * Does not mutate rates, profit, fees, ownership, or BestChange position.
 */
final class AdminPricingTransparency
{
    /** BestChange compiler cadence is typically under a few minutes. */
    public const BC_HEALTHY_SECONDS = 300;

    public const BC_AGING_SECONDS = 1800;

    /** Derived/Zelle compilers often run on a slower cycle. */
    public const DERIVED_HEALTHY_SECONDS = 900;

    public const DERIVED_AGING_SECONDS = 3600;

    public const ZELLE_HEALTHY_SECONDS = 900;

    public const ZELLE_AGING_SECONDS = 3600;

    public static function explain(DirectionExchange $item): array
    {
        $item->loadMissing(['bestchange_directions', 'currency1', 'currency2']);
        $resolver = new CommercialAdjustmentResolver();
        $family = $resolver->family($item);
        $base = (string) ($item->course_value ?? '0');
        $health = BestChangeMarketBaseHealth::evaluate((int) $item->id);
        if ($family === 'BESTCHANGE' && !empty($health['rate_value']) && is_numeric((string) $health['rate_value'])) {
            $base = (string) $health['rate_value'];
        }
        $calc = CanonicalDirectionRateCalculator::make();
        $displayProfit = $resolver->displayProfitPercent($item);
        $floatingFee = (string) ($item->floating_fee ?? '0');
        $fixFee = (string) ($item->fix_fee ?? '0');

        // Owner-facing finals must match live math. For BestChange, apply «Прибыль»
        // to the source book explicitly (Canonical may also apply -profit when a
        // derived config still lists the pair; do not rely on that side-effect).
        if ($family === 'BESTCHANGE') {
            $norm = new RateFeePercentNormalizer();
            $floatAdj = bcmul($displayProfit, '-1', 8);
            $floatingFinal = $norm->applyPercentToRate($base, $floatAdj, CanonicalDirectionRateCalculator::ROUNDING_SCALE);
            $fixAdj = $norm->toPercentExpressionOrZero($fixFee)['percent'];
            $fixedFinal = $norm->applyPercentToRate($base, $fixAdj, CanonicalDirectionRateCalculator::ROUNDING_SCALE);
            $floating = new CalculatedDirectionRate(
                directionId: (int) $item->id,
                baseRate: $base,
                mode: RateMode::Floating,
                adjustmentValue: $floatAdj,
                adjustmentUnit: RateAdjustmentUnit::Percent,
                finalRate: $floatingFinal,
                roundingScale: CanonicalDirectionRateCalculator::ROUNDING_SCALE,
                sourceTimestamp: $health['updated_at'] ?? null,
                channel: RateChannel::Website,
                eligible: bccomp($floatingFinal, '0', 18) === 1,
                reason: null,
                feeClassification: 'percent',
            );
            $fixed = new CalculatedDirectionRate(
                directionId: (int) $item->id,
                baseRate: $base,
                mode: RateMode::Fixed,
                adjustmentValue: $fixAdj,
                adjustmentUnit: RateAdjustmentUnit::Percent,
                finalRate: $fixedFinal,
                roundingScale: CanonicalDirectionRateCalculator::ROUNDING_SCALE,
                sourceTimestamp: $health['updated_at'] ?? null,
                channel: RateChannel::Website,
                eligible: bccomp($fixedFinal, '0', 18) === 1,
                reason: null,
                feeClassification: 'percent',
            );
        } else {
            $floating = $calc->calculate($item, RateMode::Floating, RateChannel::Website, $base);
            $fixed = $calc->calculate($item, RateMode::Fixed, RateChannel::Website, $base);
        }
        $position = self::positionLayers($item, $health);
        $updatedAt = self::authoritativeUpdatedAt($item, $family, $health);
        $freshness = self::classifyFreshness($family, $updatedAt);
        $layers = self::commercialLayers($family, $displayProfit, $floatingFee, $fixFee, $floating, $fixed);
        $ownershipReason = self::ownershipReason($family, $item);

        return [
            'direction_id' => (int) $item->id,
            'pair' => [
                'from' => (string) ($item->currency1?->designation_xml ?? ''),
                'to' => (string) ($item->currency2?->designation_xml ?? ''),
            ],
            'ownership' => [
                'family' => $family,
                'label' => AdminRateSourcePresenter::label($item),
                'parser_source_name' => (string) ($item->parser_source_name ?? ''),
                'reason' => $ownershipReason,
                'profit_survives_refresh' => true,
                'profit_survives_hint' => 'Коммерческая настройка (Прибыль) сохраняется при автообновлении курса.',
            ],
            'position' => $position,
            'source_rate' => [
                'base' => $base,
                'base_source' => self::baseSourceLabel($family, $health),
                'bestchange_rate' => $health['rate_value'] ?? null,
                'bestchange_status' => $health['status'] ?? null,
            ],
            'commercial' => [
                'explicit_profit_percent' => $displayProfit,
                'storage_field' => $resolver->storageField($item),
                'floating_fee' => $floatingFee,
                'fix_fee' => $fixFee,
                'other_adjustments_active' => self::otherAdjustmentsActive($family, $displayProfit, $floatingFee, $fixFee, $position),
                'zero_profit_wording' => self::zeroProfitWording($family, $displayProfit, $floatingFee, $fixFee, $position),
            ],
            'layers' => $layers,
            'customer_rates' => [
                'floating' => $floating->finalRate,
                'fixed' => $fixed->finalRate,
            ],
            'freshness' => $freshness,
            'help' => $resolver->helpText($item),
            // Back-compat keys for existing overlay consumers.
            'base' => $base,
            'profit' => $displayProfit,
            'final_floating' => $floating->finalRate,
            'final_fixed' => $fixed->finalRate,
            'fix_fee' => $fixFee,
            'base_source' => self::baseSourceLabel($family, $health),
            'source_status' => $freshness['state_label'],
            'bestchange_position' => $position['effective'],
            'bestchange_rate' => $health['rate_value'] ?? null,
            'source_updated_at' => $updatedAt,
            'parser_source_name' => (string) ($item->parser_source_name ?? ''),
        ];
    }

    /**
     * Compact pricing health snapshot for admin strip / API.
     *
     * @return array<string, mixed>
     */
    public static function healthSnapshot(): array
    {
        $owners = DB::table('direction_exchange')
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->selectRaw("CASE
              WHEN parser_source_name='BestChange' THEN 'BESTCHANGE'
              WHEN parser_source_name='DERIVED_MARKET_BASELINE' THEN 'DERIVED'
              WHEN parser_source_name='ZELLE_USDTTRC20_BENCHMARK' THEN 'ZELLE'
              WHEN parser_source_name IN ('Ручной курс','Manual') THEN 'MANUAL'
              ELSE 'UNKNOWN' END o, COUNT(*) c")
            ->groupBy('o')
            ->pluck('c', 'o')
            ->all();

        $zeroProfit = [
            'BESTCHANGE' => (int) DB::table('direction_exchange')
                ->where('status', 1)->whereNull('deleted_at')
                ->where('parser_source_name', 'BestChange')
                ->whereRaw('CAST(COALESCE(profit,0) AS DECIMAL(20,8)) = 0')
                ->count(),
            'DERIVED' => (int) DB::table('direction_exchange')
                ->where('status', 1)->whereNull('deleted_at')
                ->where('parser_source_name', 'DERIVED_MARKET_BASELINE')
                ->whereRaw('CAST(COALESCE(profit,0) AS DECIMAL(20,8)) = 0')
                ->count(),
            'ZELLE' => (int) DB::table('direction_exchange')
                ->where('status', 1)->whereNull('deleted_at')
                ->where('parser_source_name', 'ZELLE_USDTTRC20_BENCHMARK')
                ->whereRaw('CAST(COALESCE(floating_fee,0) AS DECIMAL(20,8)) = 0')
                ->count(),
            'MANUAL' => (int) DB::table('direction_exchange')
                ->where('status', 1)->whereNull('deleted_at')
                ->whereIn('parser_source_name', ['Ручной курс', 'Manual'])
                ->whereRaw('CAST(COALESCE(profit,0) AS DECIMAL(20,8)) = 0')
                ->count(),
        ];

        $lastBc = DB::table('bestchange_directions')
            ->where('status', 1)
            ->where('is_error_parser', 0)
            ->whereRaw('CAST(rate_value AS DECIMAL(36,18)) > 0')
            ->max('updated_at');

        // Critical = parser error or severely lagged (>2h). Mild aging (>30m) is reported separately.
        $staleCritical = (int) DB::table('bestchange_directions as bc')
            ->join('direction_exchange as de', 'de.id', '=', 'bc.id_direction_exchange')
            ->where('de.status', 1)->whereNull('de.deleted_at')
            ->where('de.parser_source_name', 'BestChange')
            ->where('bc.status', 1)
            ->where(function ($q) {
                $q->where('bc.is_error_parser', 1)
                    ->orWhereRaw('CAST(bc.rate_value AS DECIMAL(36,18)) <= 0')
                    ->orWhereRaw('bc.updated_at < DATE_SUB(NOW(), INTERVAL ? SECOND)', [self::BC_AGING_SECONDS * 4]);
            })
            ->count();

        $agingCount = (int) DB::table('bestchange_directions as bc')
            ->join('direction_exchange as de', 'de.id', '=', 'bc.id_direction_exchange')
            ->where('de.status', 1)->whereNull('de.deleted_at')
            ->where('de.parser_source_name', 'BestChange')
            ->where('bc.status', 1)
            ->where('bc.is_error_parser', 0)
            ->whereRaw('CAST(bc.rate_value AS DECIMAL(36,18)) > 0')
            ->whereRaw('bc.updated_at < DATE_SUB(NOW(), INTERVAL ? SECOND)', [self::BC_HEALTHY_SECONDS])
            ->whereRaw('bc.updated_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)', [self::BC_AGING_SECONDS * 4])
            ->count();

        $dupGroups = (int) DB::table('bestchange_directions')
            ->select('id_direction_exchange')
            ->groupBy('id_direction_exchange')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        $dupBothActive = (int) DB::table('bestchange_directions')
            ->select('id_direction_exchange')
            ->groupBy('id_direction_exchange')
            ->havingRaw('SUM(status = 1) > 1')
            ->get()
            ->count();

        $cfg = new BestChangeConfig();
        $lastBcAge = null;
        $lastBcHuman = null;
        if ($lastBc) {
            $ts = strtotime((string) $lastBc);
            if ($ts !== false) {
                $lastBcAge = max(0, time() - $ts);
                $lastBcHuman = self::humanAge($lastBcAge);
            }
        }

        $healthy = ($owners['UNKNOWN'] ?? 0) === 0 && $dupBothActive === 0;

        return [
            'healthy' => $healthy,
            'ownership_counts' => [
                'BESTCHANGE' => (int) ($owners['BESTCHANGE'] ?? 0),
                'DERIVED' => (int) ($owners['DERIVED'] ?? 0),
                'ZELLE' => (int) ($owners['ZELLE'] ?? 0),
                'MANUAL' => (int) ($owners['MANUAL'] ?? 0),
                'UNKNOWN' => (int) ($owners['UNKNOWN'] ?? 0),
            ],
            'zero_profit' => $zeroProfit,
            'stale_critical_count' => $staleCritical,
            'aging_count' => $agingCount,
            'last_bestchange_update_at' => $lastBc,
            'last_bestchange_update_age_seconds' => $lastBcAge,
            'last_bestchange_update_human' => $lastBcHuman,
            'global_bestchange_position' => (int) $cfg->position(),
            'global_rate_mode' => (string) $cfg->rateMode(),
            'duplicate_mapping_groups' => $dupGroups,
            'duplicate_both_active' => $dupBothActive,
            'runtime_ambiguity' => $dupBothActive > 0,
            'generated_at' => gmdate('c'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function positionLayers(DirectionExchange $item, array $health): array
    {
        $cfg = new BestChangeConfig();
        $global = (int) $cfg->position();
        $globalMode = (string) $cfg->rateMode();
        $bc = $item->bestchange_directions;
        $pairRaw = null;
        $pairMode = null;
        if ($bc instanceof BestChangeDirection) {
            $raw = trim((string) ($bc->position_num ?? ''));
            $pairRaw = $raw !== '' ? $raw : null;
            $pairMode = trim((string) ($bc->rate_mode ?? ''));
            $pairMode = $pairMode !== '' ? $pairMode : null;
        }
        $effective = $health['position_num'] ?? null;
        if ($effective === null && $pairRaw !== null && is_numeric($pairRaw)) {
            $effective = (int) $pairRaw;
        }
        if ($effective === null) {
            $effective = $global;
        }
        $hasOverride = $pairRaw !== null && (string) $pairRaw !== (string) $global;

        return [
            'global' => $global,
            'global_rate_mode' => $globalMode,
            'pair_override' => $pairRaw,
            'pair_rate_mode' => $pairMode,
            'effective' => $effective,
            'has_pair_override' => $hasOverride,
            'authoritative_for_customer_rate' => AdminRateSourcePresenter::isBestChangePositionAuthoritative($item),
        ];
    }

    private static function authoritativeUpdatedAt(DirectionExchange $item, string $family, array $health): ?string
    {
        if ($family === 'BESTCHANGE' && !empty($health['updated_at'])) {
            return (string) $health['updated_at'];
        }
        $bc = $item->bestchange_directions;
        if ($bc instanceof BestChangeDirection && !empty($bc->updated_at)) {
            return (string) $bc->updated_at;
        }
        if (!empty($item->updated_at)) {
            return (string) $item->updated_at;
        }

        return null;
    }

    /**
     * @return array{state:string,state_label:string,age_seconds:?int,updated_at:?string,human:?string}
     */
    private static function classifyFreshness(string $family, ?string $updatedAt): array
    {
        if ($updatedAt === null || $updatedAt === '') {
            return [
                'state' => 'SOURCE_ERROR',
                'state_label' => 'Нет данных об обновлении',
                'age_seconds' => null,
                'updated_at' => null,
                'human' => null,
            ];
        }
        $ts = strtotime($updatedAt);
        if ($ts === false) {
            return [
                'state' => 'SOURCE_ERROR',
                'state_label' => 'Ошибка времени источника',
                'age_seconds' => null,
                'updated_at' => $updatedAt,
                'human' => null,
            ];
        }
        $age = max(0, time() - $ts);
        [$healthy, $aging] = match ($family) {
            'BESTCHANGE' => [self::BC_HEALTHY_SECONDS, self::BC_AGING_SECONDS],
            'ZELLE' => [self::ZELLE_HEALTHY_SECONDS, self::ZELLE_AGING_SECONDS],
            default => [self::DERIVED_HEALTHY_SECONDS, self::DERIVED_AGING_SECONDS],
        };
        if ($age <= $healthy) {
            $state = 'HEALTHY';
            $label = 'Актуально';
        } elseif ($age <= $aging) {
            $state = 'AGING';
            $label = 'Устаревает';
        } else {
            $state = 'STALE';
            $label = 'Устарело — проверьте';
        }

        return [
            'state' => $state,
            'state_label' => $label,
            'age_seconds' => $age,
            'updated_at' => $updatedAt,
            'human' => self::humanAge($age),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function commercialLayers(
        string $family,
        string $displayProfit,
        string $floatingFee,
        string $fixFee,
        CalculatedDirectionRate $floating,
        CalculatedDirectionRate $fixed,
    ): array {
        $profitApplied = match ($family) {
            'BESTCHANGE', 'DERIVED' => true,
            'ZELLE' => true, // stored as floating_fee = -profit
            default => true,
        };
        $floatingExtra = match ($family) {
            // BestChange: floating commercial is profit via Calculator; Canonical floating adj = 0.
            'BESTCHANGE' => '0',
            'DERIVED' => '0', // profit mapped into Canonical floating fee
            'ZELLE' => $floatingFee,
            default => $floatingFee,
        };

        return [
            'is_profit_applied_to_source_rate' => $profitApplied,
            'is_floating_fee_additional' => $family === 'ZELLE' || (abs((float) $floatingFee) > 0 && $family === 'OTHER'),
            'is_fixed_fee_additional' => abs((float) $fixFee) > 0.0000001,
            'when_each_applies' => match ($family) {
                'BESTCHANGE' => 'Позиция BestChange задаёт базовый курс. Прибыль % применяется к нему для плавающего курса клиента. fix_fee дополнительно только в фиксированном режиме.',
                'DERIVED' => 'База внутреннего рынка. Прибыль % уменьшает/увеличивает плавающий курс клиента. fix_fee — только фиксированный режим.',
                'ZELLE' => 'База Zelle (USDTTRC20-бенчмарк). Прибыль хранится как floating_fee (−Прибыль). Фиксированный режим: сначала floating, затем fix_fee.',
                default => 'Прибыль и комиссии применяются по правилам семейства источника.',
            },
            'floating_adjustment_percent' => $family === 'ZELLE' ? $floatingFee : (string) ((float) $displayProfit * -1),
            'fixed_adjustment_percent' => $fixFee,
            'floating_adjustment_applied' => (string) $floating->adjustmentValue,
            'fixed_adjustment_applied' => (string) $fixed->adjustmentValue,
        ];
    }

    private static function otherAdjustmentsActive(
        string $family,
        string $displayProfit,
        string $floatingFee,
        string $fixFee,
        array $position,
    ): bool {
        if (abs((float) $fixFee) > 0.0000001) {
            return true;
        }
        if ($family === 'BESTCHANGE' && !empty($position['authoritative_for_customer_rate'])) {
            // Position itself is a commercial lever even when profit=0.
            return true;
        }
        if ($family === 'ZELLE' && abs((float) $floatingFee) > 0.0000001) {
            return true;
        }

        return false;
    }

    private static function zeroProfitWording(
        string $family,
        string $displayProfit,
        string $floatingFee,
        string $fixFee,
        array $position,
    ): string {
        $explicitZero = abs((float) $displayProfit) < 0.0000001;
        if (!$explicitZero) {
            return 'Явная прибыль: ' . $displayProfit . '%';
        }
        $parts = ['Явная прибыль: 0%'];
        if ($family === 'BESTCHANGE' && !empty($position['authoritative_for_customer_rate'])) {
            $parts[] = 'Маржа может задаваться позицией BestChange';
        }
        if (abs((float) $fixFee) > 0.0000001) {
            $parts[] = 'Другие корректировки: активны (fix_fee=' . $fixFee . '%)';
        } elseif ($family === 'ZELLE' && abs((float) $floatingFee) > 0.0000001) {
            $parts[] = 'Другие корректировки: активны (floating_fee=' . $floatingFee . '%)';
        } else {
            $parts[] = 'Доп. процентные корректировки: нет';
        }

        return implode('. ', $parts);
    }

    private static function ownershipReason(string $family, DirectionExchange $item): string
    {
        return match ($family) {
            'BESTCHANGE' => 'Назначен BestChange (явная привязка источника)',
            'DERIVED' => 'Внутренний рынок / derived baseline',
            'ZELLE' => 'Политика Zelle (свой курс от USDTTRC20-бенчмарка)',
            default => str_contains(mb_strtolower((string) ($item->parser_source_name ?? '')), 'ручн')
                || strcasecmp((string) ($item->parser_source_name ?? ''), 'Manual') === 0
                ? 'Ручной курс (явный override)'
                : 'Ручной или иной источник',
        };
    }

    private static function baseSourceLabel(string $family, array $health): string
    {
        if ($family === 'BESTCHANGE') {
            return 'BestChange';
        }
        if ($family === 'ZELLE') {
            return 'Zelle';
        }
        if ($family === 'DERIVED') {
            return !empty($health['healthy']) ? 'DERIVED_WITH_BC_LINK' : 'DERIVED';
        }

        return 'OTHER';
    }

    private static function humanAge(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . ' сек назад';
        }
        if ($seconds < 3600) {
            return (int) floor($seconds / 60) . ' мин назад';
        }
        if ($seconds < 86400) {
            return (int) floor($seconds / 3600) . ' ч назад';
        }

        return (int) floor($seconds / 86400) . ' дн назад';
    }
}
