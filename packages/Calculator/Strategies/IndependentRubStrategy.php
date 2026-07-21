<?php

declare(strict_types=1);

namespace iEXPackages\Calculator\Strategies;

use App\Models\DirectionExchange;
use App\Services\Rates\IndependentMarketBaseline;
use App\Services\Rates\RubFamilyPremiumPolicy;
use iEXPackages\Calculator\Contracts\StrategyInterface;
use Throwable;

/**
 * Canonical RUB source rate built only from approved independent providers.
 *
 * Existing calculator adjustments (group fee, limits and direction profit)
 * are deliberately applied later by Calculator exactly once.
 */
final class IndependentRubStrategy implements StrategyInterface
{
    public function __construct(
        private readonly DirectionExchange $directionExchange,
        private readonly ?IndependentMarketBaseline $baseline = null,
        private readonly ?RubFamilyPremiumPolicy $policy = null,
    ) {
    }

    public function getRate(): string
    {
        try {
            if (
                !$this->directionExchange->relationLoaded('currency1')
                || !$this->directionExchange->relationLoaded('currency2')
            ) {
                $this->directionExchange->loadMissing(['currency1', 'currency2']);
            }
            $from = strtoupper((string) ($this->directionExchange->currency1?->designation_xml ?? ''));
            $to = strtoupper((string) ($this->directionExchange->currency2?->designation_xml ?? ''));
            if (!str_contains($to, 'RUB')) {
                return '0';
            }

            $asset = IndependentMarketBaseline::assetFromCode($from);
            if ($asset === null) {
                return '0';
            }

            $baseline = $this->baseline ?? new IndependentMarketBaseline();
            $quote = in_array($asset, ['USDT', 'USDC'], true)
                ? $baseline->quote('USDRUB')
                : $baseline->cryptoRub($asset);
            if (
                $quote === null
                || ($quote['source_type'] ?? null) !== 'INDEPENDENT_PRIMARY'
                || !empty($quote['circular_source_detected'])
            ) {
                return '0';
            }

            $premium = ($this->policy ?? RubFamilyPremiumPolicy::fromStorageApp())
                ->canonicalSourcePremiumPercent($to);
            if ($premium === null || $premium < 0) {
                return '0';
            }

            return bcmul(
                (string) $quote['rate'],
                bcadd('1', bcdiv((string) $premium, '100', IndependentMarketBaseline::SCALE), IndependentMarketBaseline::SCALE),
                IndependentMarketBaseline::SCALE,
            );
        } catch (Throwable) {
            return '0';
        }
    }
}
