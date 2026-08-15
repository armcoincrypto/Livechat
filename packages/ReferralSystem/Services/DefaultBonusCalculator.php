<?php

declare(strict_types=1);

namespace iEXPackages\ReferralSystem\Services;

use iEXPackages\ReferralSystem\Contracts\BonusCalculator;
use iEXPackages\ReferralSystem\Contracts\ProfitResolver;
use iEXPackages\ReferralSystem\DTO\ReferralContext;
use iEXPackages\ReferralSystem\DTO\ReferralMoney;
use iEXPackages\ReferralSystem\Support\ReferralMath;

class DefaultBonusCalculator implements BonusCalculator
{
    public function __construct(
        private ProfitResolver $profitResolver,
    ) {}

    public function calculate(ReferralContext $context, ReferralMoney $base): array
    {
        $dir = $context->direction;
        $partner = $context->partner;

        // fixed payout
        $fixed = ReferralMath::norm((string) ($dir->fixed_payout ?? '0'));
        if (!ReferralMath::isZero($fixed)) {
            return $this->result(
                amount: $fixed,
                percent: '0',
                fixed: $fixed,
                method: 'fixed',
                profitPercent: '0',
                profitFixed: '0',
                exchangeProfit: '0',
                profitBaseAfter: '0'
            );
        }

        // percent: personal -> individual -> program (with max_percent_partner)
        $percent = ReferralMath::norm((string) ($partner->personal_ref_discount ?? '0'), ReferralMath::PERCENT_SCALE);

        if (ReferralMath::isZero($percent, ReferralMath::PERCENT_SCALE)) {
            $percent = ReferralMath::norm((string) ($dir->individual_percentage ?? '0'), ReferralMath::PERCENT_SCALE);
        }

        if (ReferralMath::isZero($percent, ReferralMath::PERCENT_SCALE)) {
            $p = ReferralMath::norm((string) ($context->referralLink?->program?->percent ?? '0'), ReferralMath::PERCENT_SCALE);

            $maxPercentPartner = ReferralMath::norm((string) ($dir->max_percent_partner ?? '0'), ReferralMath::PERCENT_SCALE);
            if (!ReferralMath::isZero($maxPercentPartner, ReferralMath::PERCENT_SCALE) && ReferralMath::cmp($p, $maxPercentPartner, ReferralMath::PERCENT_SCALE) === 1) {
                $p = $maxPercentPartner;
            }

            $percent = $p;
        }

        // partner max_ref_discount
        $maxRef = ReferralMath::norm((string) ($partner->max_ref_discount ?? '0'), ReferralMath::PERCENT_SCALE);
        if (!ReferralMath::isZero($maxRef, ReferralMath::PERCENT_SCALE) && ReferralMath::cmp($percent, $maxRef, ReferralMath::PERCENT_SCALE) === 1) {
            $percent = $maxRef;
        }

        // method
        $partnerMethod = (int) ($partner->partner_method ?? 0);
        $globalType = (int) iEXSetting('type_partner_deductions'); // 1 => profit

        $method = match ($partnerMethod) {
            1 => 'exchange',
            2 => 'profit',
            default => ($globalType === 1 ? 'profit' : 'exchange'),
        };

        if (ReferralMath::isZero($base->amount) || ReferralMath::isZero($percent, ReferralMath::PERCENT_SCALE)) {
            return $this->result('0', $percent, '0', $method, '0', '0', '0', $base->amount);
        }

        if ($method === 'exchange') {
            $amount = $this->mulPercent($base->amount, $percent);
            return $this->result($amount, $percent, '0', 'exchange', '0', '0', '0', $base->amount);
        }

        // profit
        $profits = $this->profitResolver->resolve($context);
        $profitPercent = ReferralMath::norm((string) ($profits['percentage'] ?? '0'), ReferralMath::PERCENT_SCALE);
        $profitFixed   = ReferralMath::norm((string) ($profits['fixed'] ?? '0'));

        $profitBaseAfter = $this->baseAfterProfitDeductions($base->amount, $profitPercent, $profitFixed);
        $exchangeProfit  = ReferralMath::sub($base->amount, $profitBaseAfter);

        if (ReferralMath::cmp($exchangeProfit, '0') === -1) {
            $exchangeProfit = '0';
        }

        $amount = $this->mulPercent($exchangeProfit, $percent);

        return $this->result($amount, $percent, '0', 'profit', $profitPercent, $profitFixed, $exchangeProfit, $profitBaseAfter);
    }

    private function mulPercent(string $value, string $percent): string
    {
        // value * (percent/100)
        $p = ReferralMath::div($percent, '100', ReferralMath::PERCENT_SCALE);
        return ReferralMath::mul($value, $p);
    }

    private function baseAfterProfitDeductions(string $base, string $profitPercent, string $profitFixed): string
    {
        $after = $base;

        if (!ReferralMath::isZero($profitPercent, ReferralMath::PERCENT_SCALE)) {
            $delta = $this->mulPercent($after, $profitPercent);
            $after = ReferralMath::sub($after, $delta);
        }

        if (!ReferralMath::isZero($profitFixed)) {
            $after = ReferralMath::sub($after, $profitFixed);
        }

        if (ReferralMath::cmp($after, '0') === -1) {
            return '0';
        }

        return $after;
    }

    private function result(
        string $amount,
        string $percent,
        string $fixed,
        string $method,
        string $profitPercent,
        string $profitFixed,
        string $exchangeProfit,
        string $profitBaseAfter
    ): array {
        return [
            'amount' => ReferralMath::norm($amount),
            'percent' => ReferralMath::norm($percent, ReferralMath::PERCENT_SCALE),
            'fixed' => ReferralMath::norm($fixed),
            'method' => $method,

            'profit_percent' => ReferralMath::norm($profitPercent, ReferralMath::PERCENT_SCALE),
            'profit_fixed' => ReferralMath::norm($profitFixed),
            'exchange_profit' => ReferralMath::norm($exchangeProfit),
            'profit_base_after' => ReferralMath::norm($profitBaseAfter),
        ];
    }
}
