<?php

declare(strict_types=1);

namespace iEXPackages\ReferralSystem\Services;

use iEXPackages\ReferralSystem\Contracts\BonusBaseResolver;
use iEXPackages\ReferralSystem\Contracts\CurrencyConverter;
use iEXPackages\ReferralSystem\DTO\ReferralContext;
use iEXPackages\ReferralSystem\DTO\ReferralMoney;
use iEXPackages\ReferralSystem\Support\ReferralMath;

class DefaultBonusBaseResolver implements BonusBaseResolver
{
    public function __construct(
        private CurrencyConverter $converter,
    ) {}

    public function resolveBase(ReferralContext $context): ReferralMoney
    {
        $task = $context->task;
        if (!$task) {
            return ReferralMoney::zero($context->bonusCurrency, ReferralMath::SCALE);
        }

        // база: give_price_with_comm
        $amountGive = ReferralMath::norm((string) ($task->give_price_with_comm ?? '0'));
        if (ReferralMath::isZero($amountGive)) {
            return ReferralMoney::zero($context->bonusCurrency, ReferralMath::SCALE);
        }

        $fromCode = trim((string) ($context->direction->currency1?->code_currency?->name ?? ''));
        if ($fromCode === '') {
            return ReferralMoney::zero($context->bonusCurrency, ReferralMath::SCALE);
        }

        // internal_rate (опционально)
        $internalRateRaw = (string) ($context->direction->currency1?->code_currency?->internal_rate ?? '0');
        $internalRate = ReferralMath::isZero($internalRateRaw) ? null : ReferralMath::norm($internalRateRaw);

        $converted = $this->converter->convert(
            $fromCode,
            $context->bonusCurrency,
            $amountGive,
            $internalRate
        );

        // base живёт в CALC масштабе, но у нас SCALE=2, так что достаточно
        return new ReferralMoney($converted, $context->bonusCurrency, ReferralMath::SCALE);
    }
}
