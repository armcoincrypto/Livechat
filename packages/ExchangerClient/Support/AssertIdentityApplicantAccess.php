<?php
declare(strict_types=1);

namespace iEXPackages\ExchangerClient\Support;

use App\Models\SumsubId;

/**
 * Server-side SumSub applicant ownership for identity KYC.
 * applicantId is metadata — never authorization by itself.
 */
final class AssertIdentityApplicantAccess
{
    public static function ownedApplicantId(int $userId): ?string
    {
        if ($userId <= 0) {
            return null;
        }

        $applicantId = SumsubId::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->value('applicant_id');

        $applicantId = is_string($applicantId) ? trim($applicantId) : '';

        return $applicantId !== '' ? $applicantId : null;
    }

    /**
     * Exact-match ownership check (constant-time string compare).
     */
    public static function matches(?string $ownedApplicantId, string $providedApplicantId): bool
    {
        if ($ownedApplicantId === null || $ownedApplicantId === '') {
            return false;
        }

        $provided = trim($providedApplicantId);
        if ($provided === '') {
            return false;
        }

        return hash_equals($ownedApplicantId, $provided);
    }

    public static function allows(int $userId, string $providedApplicantId): bool
    {
        return self::matches(self::ownedApplicantId($userId), $providedApplicantId);
    }
}
