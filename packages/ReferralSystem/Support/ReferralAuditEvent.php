<?php

declare(strict_types=1);

namespace iEXPackages\ReferralSystem\Support;

class ReferralAuditEvent
{
    public const CLIENT_ATTACHED   = 'client_attached';
    public const ATTACH_FAILED     = 'attach_failed';
    public const SELF_REFERRAL     = 'self_referral';

    public const CREDIT_SUCCESS    = 'credit_success';
    public const CREDIT_DUPLICATE  = 'credit_duplicate';
    public const CREDIT_INELIGIBLE = 'credit_ineligible';
    public const CREDIT_ERROR      = 'credit_error';

    public const PREVIEW           = 'preview';
    public const PROGRAM_ASSIGNED  = 'program_assigned';
    public const BALANCE_CREATED   = 'balance_created';
    public const REFERRAL_CAPTURED = 'referral_captured';
    public const MANUAL_PARTNER_ADJUSTMENT = 'manual_partner_adjustment';
    public const MANUAL_PARTNER_ADJUSTMENT_REVERSED = 'manual_partner_adjustment_reversed';
}
