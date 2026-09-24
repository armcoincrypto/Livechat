<?php

declare(strict_types=1);

/**
 * Wave A classification — legacy ops script interaction.
 *
 * Script: scripts/bestchange-manual-partner-adjustment.php
 *
 * purpose:
 *   Governed one-off manual partner balance adjustment (P-BESTCHANGE-9).
 *   Absolute/set-style correction with user_balance_log + referral_audit_logs.
 *
 * authorization:
 *   CLI only; hardcoded PARTNER_USER_ID / APPROVED_BY_MANAGER_ID.
 *   Requires explicit --dry-run or --apply. Not a public endpoint.
 *
 * locking:
 *   DEBT: does not use lockForUpdate on user_balance (as of Wave A base).
 *   Can race with PartnerAccrualCreditService and PartnerWithdrawalController.
 *
 * audit:
 *   Writes user_balance_log (from/to) and referral_audit_logs event.
 *
 * Wave A decision:
 *   Do not delete. Do not redesign in Wave A.
 *   Classify as existing debt. Accrual service uses FOR UPDATE; ops script
 *   should be upgraded to lockForUpdate in a later hardening wave if still used.
 *
 * EXISTING_DEBT=YES
 * WAVE_A_FIX_REQUIRED_FOR_CORRECTNESS=NO
 */
