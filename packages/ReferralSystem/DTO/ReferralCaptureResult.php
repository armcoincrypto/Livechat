<?php

declare(strict_types=1);

namespace iEXPackages\ReferralSystem\DTO;

final class ReferralCaptureResult
{
    public function __construct(
        public ?string $code = null,
        public int $linkId = 0,
        public ?string $source = null,
        public ?int $partnerUserId = null,
    ) {}

    public function hasCode(): bool
    {
        return $this->code !== null && trim($this->code) !== '';
    }
}
