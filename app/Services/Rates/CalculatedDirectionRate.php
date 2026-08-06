<?php

declare(strict_types=1);

namespace App\Services\Rates;

final class CalculatedDirectionRate
{
    public function __construct(
        public readonly int $directionId,
        public readonly string $baseRate,
        public readonly RateMode $mode,
        public readonly string $adjustmentValue,
        public readonly RateAdjustmentUnit $adjustmentUnit,
        public readonly string $finalRate,
        public readonly int $roundingScale,
        public readonly ?string $sourceTimestamp,
        public readonly RateChannel $channel,
        public readonly bool $eligible,
        public readonly ?string $reason = null,
        public readonly string $feeClassification = RateFeePercentNormalizer::CLASS_ZERO,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'direction_id' => $this->directionId,
            'base_rate' => $this->baseRate,
            'mode' => $this->mode->value,
            'adjustment_value' => $this->adjustmentValue,
            'adjustment_unit' => $this->adjustmentUnit->value,
            'final_rate' => $this->finalRate,
            'rounding_scale' => $this->roundingScale,
            'source_timestamp' => $this->sourceTimestamp,
            'channel' => $this->channel->value,
            'eligibility' => $this->eligible,
            'reason' => $this->reason,
            'fee_classification' => $this->feeClassification,
        ];
    }
}
