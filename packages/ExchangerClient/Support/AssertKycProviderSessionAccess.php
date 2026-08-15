<?php

declare(strict_types=1);

namespace iEXPackages\ExchangerClient\Support;

use App\Models\KycProviderSession;

/**
 * Ownership checks for provider session ids (Didit).
 */
final class AssertKycProviderSessionAccess
{
    public static function ownedSessionId(int $userId, string $provider = 'didit'): ?string
    {
        if ($userId <= 0) {
            return null;
        }

        $sessionId = KycProviderSession::query()
            ->where('user_id', $userId)
            ->where('provider', $provider)
            ->orderByDesc('id')
            ->value('provider_session_id');

        $sessionId = is_string($sessionId) ? trim($sessionId) : '';

        return $sessionId !== '' ? $sessionId : null;
    }

    public static function allows(int $userId, string $providedSessionId, string $provider = 'didit'): bool
    {
        $owned = self::ownedSessionId($userId, $provider);
        if ($owned === null) {
            return false;
        }

        $provided = trim($providedSessionId);
        if ($provided === '') {
            return false;
        }

        return hash_equals($owned, $provided);
    }
}
