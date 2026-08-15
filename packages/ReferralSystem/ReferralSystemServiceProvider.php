<?php

declare(strict_types=1);

namespace iEXPackages\ReferralSystem;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

use iEXPackages\ReferralSystem\Contracts\BonusBaseResolver;
use iEXPackages\ReferralSystem\Contracts\BonusCalculator;
use iEXPackages\ReferralSystem\Contracts\CurrencyConverter;
use iEXPackages\ReferralSystem\Contracts\EligibilityRule;
use iEXPackages\ReferralSystem\Contracts\PayoutLimiter;
use iEXPackages\ReferralSystem\Contracts\ProfitResolver;

use iEXPackages\ReferralSystem\Rules\DirectionPartnerEnabledRule;
use iEXPackages\ReferralSystem\Rules\EnabledRule;
use iEXPackages\ReferralSystem\Rules\PartnerEmailVerifiedRule;
use iEXPackages\ReferralSystem\Rules\PartnerExistsRule;
use iEXPackages\ReferralSystem\Rules\PartnerPayoutNotDisabledRule;

use iEXPackages\ReferralSystem\Services\DefaultBonusBaseResolver;
use iEXPackages\ReferralSystem\Services\DefaultBonusCalculator;
use iEXPackages\ReferralSystem\Services\DefaultCurrencyConverter;
use iEXPackages\ReferralSystem\Services\DefaultPayoutLimiter;
use iEXPackages\ReferralSystem\Services\DefaultProfitResolver;
use iEXPackages\ReferralSystem\Services\ReferralAuditLogger;
use iEXPackages\ReferralSystem\Services\ReferralBonusConverter;
use iEXPackages\ReferralSystem\ReferralEngine;

final class ReferralSystemServiceProvider extends ServiceProvider implements DeferrableProvider
{
    private const RULES_TAG = 'referral.eligibility.rules';

    public function register(): void
    {
        // Всё остальное — bind: создаётся только если нужно
        $this->app->bind(ReferralCookieManager::class);
        $this->app->bind(ReferralAuditLogger::class);
        $this->app->bind(\iEXPackages\ReferralSystem\Services\ReferralCaptureResolver::class);

        $this->app->bind(ReferralBonusConverter::class);

        $this->app->bind(CurrencyConverter::class, function ($app) {
            return new DefaultCurrencyConverter(
                bonusConverter: $app->make(ReferralBonusConverter::class),
            );
        });

        $this->app->bind(BonusBaseResolver::class, DefaultBonusBaseResolver::class);
        $this->app->bind(ProfitResolver::class, DefaultProfitResolver::class);
        $this->app->bind(BonusCalculator::class, DefaultBonusCalculator::class);
        $this->app->bind(PayoutLimiter::class, DefaultPayoutLimiter::class);

        $rules = [
            EnabledRule::class,
            PartnerExistsRule::class,
            PartnerEmailVerifiedRule::class,
            PartnerPayoutNotDisabledRule::class,
            DirectionPartnerEnabledRule::class,
        ];

        foreach ($rules as $ruleClass) {
            $this->app->bind($ruleClass);
        }

        $this->app->tag($rules, self::RULES_TAG);

        $this->app->singleton(ReferralEngine::class, function ($app) {
            /** @var iterable<EligibilityRule> $rules */
            $rules = $app->tagged(self::RULES_TAG);

            $rulesArray = [];
            foreach ($rules as $rule) {
                $rulesArray[] = $rule;
            }

            return new ReferralEngine(
                baseResolver: $app->make(BonusBaseResolver::class),
                calculator:   $app->make(BonusCalculator::class),
                limiter:      $app->make(PayoutLimiter::class),
                rules:        $rulesArray,
                audit:        $app->make(ReferralAuditLogger::class),
            );
        });

        // Facade accessor key
        $this->app->alias(ReferralEngine::class, 'referral_system');
    }

    public function provides(): array
    {
        return [
            ReferralEngine::class,
            'referral_system',

            ReferralCookieManager::class,
            ReferralAuditLogger::class,
            ReferralBonusConverter::class,

            CurrencyConverter::class,
            BonusBaseResolver::class,
            ProfitResolver::class,
            BonusCalculator::class,
            PayoutLimiter::class,

            // rules
            EnabledRule::class,
            PartnerExistsRule::class,
            PartnerEmailVerifiedRule::class,
            PartnerPayoutNotDisabledRule::class,
            DirectionPartnerEnabledRule::class,
        ];
    }
}
