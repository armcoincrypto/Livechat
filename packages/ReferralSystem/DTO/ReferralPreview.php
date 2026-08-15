<?php

declare(strict_types=1);

namespace iEXPackages\ReferralSystem\DTO;

class ReferralPreview
{
    public function __construct(
        public bool $eligible,
        public string $message,

        public ?int $partnerId = null,
        public ?int $clientId = null,
        public ?int $referralLinkId = null,
        public ?int $referralProgramId = null,

        public ?string $baseAmount = null,
        public ?string $amount = null,
        public ?string $currency = null,

        public ?string $method = null,
        public ?string $percent = null,
        public ?string $fixed = null,

        public ?string $profitPercent = null,   // profit_partner (%)
        public ?string $profitFixed = null,     // profit_partner_s
        public ?string $exchangeProfit = null,  // прибыль обменника (в валюте бонуса)
        public ?string $profitBaseAfter = null, // база после вычета прибыли (опционально)
    ) {}
}
