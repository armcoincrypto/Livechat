<?php

declare(strict_types=1);

namespace iEXPackages\KYCPlugin\Services;

use App\Models\KycProviderSession;
use App\Models\SumsubId;
use App\Models\User;
use App\Models\UserVerification;

/**
 * Selects SumSub vs Didit without disrupting active SumSub sessions,
 * sticky Didit sessions, or manual-pending local verification.
 */
final class KycProviderResolver
{
    public const PROVIDER_SUMSUB = 'sumsub';
    public const PROVIDER_DIDIT = 'didit';

    public function configuredProvider(): string
    {
        $provider = strtolower((string) config('kyc.provider', self::PROVIDER_SUMSUB));

        return in_array($provider, [self::PROVIDER_SUMSUB, self::PROVIDER_DIDIT], true)
            ? $provider
            : self::PROVIDER_SUMSUB;
    }

    public function resolveForUser(User $user): string
    {
        // Active SumSub session always stays on SumSub until completion.
        $activeSumsub = SumsubId::query()
            ->where('user_id', (int) $user->id)
            ->where(function ($q) {
                $q->whereNull('is_completed')->orWhere('is_completed', 0);
            })
            ->where(function ($q) {
                $q->whereNull('provider')
                    ->orWhere('provider', self::PROVIDER_SUMSUB)
                    ->orWhere('provider', '');
            })
            ->exists();

        if ($activeSumsub) {
            return self::PROVIDER_SUMSUB;
        }

        // Existing active Didit session sticks to Didit.
        $activeDidit = KycProviderSession::query()
            ->where('user_id', (int) $user->id)
            ->where('provider', self::PROVIDER_DIDIT)
            ->whereIn('normalized_status', ['not_started', 'pending', 'manual_review'])
            ->exists();

        if ($activeDidit) {
            return self::PROVIDER_DIDIT;
        }

        // Local manual upload awaiting admin review must stay on the manual path
        // (IdentityVerifyController uses SumSub branch only when type_kyc_service!=0;
        // returning sumsub here keeps type_kyc_service=0 on UserVerification UI).
        $manualPending = UserVerification::query()
            ->where('user_id', (int) $user->id)
            ->where('status', 0)
            ->exists();

        if ($manualPending) {
            return self::PROVIDER_SUMSUB;
        }

        $allowlist = config('kyc.didit_allowlist_user_ids', []);
        $onAllowlist = is_array($allowlist)
            && $allowlist !== []
            && in_array((int) $user->id, $allowlist, true);

        // Controlled canary/cohort: allowlisted users get Didit even while global default is SumSub.
        if ($onAllowlist) {
            return self::PROVIDER_DIDIT;
        }

        if ($this->configuredProvider() !== self::PROVIDER_DIDIT) {
            return self::PROVIDER_SUMSUB;
        }

        // provider=didit with non-empty allowlist remains cohort-gated (not global default).
        // Empty allowlist + provider=didit → new clean eligible users get Didit.
        if (is_array($allowlist) && $allowlist !== []) {
            return self::PROVIDER_SUMSUB;
        }

        return self::PROVIDER_DIDIT;
    }
}
