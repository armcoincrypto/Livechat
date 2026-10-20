<?php

declare(strict_types=1);

namespace App\Services\Auth\OAuth;

use App\Models\User;
use App\Models\UserOAuthIdentity;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Idempotent backfill of users.provider/provider_id → user_oauth_identities.
 * Never fabricates subjects. Never changes deactivation/KYC/balances.
 */
final class LegacyOAuthIdentityBackfill
{
    /**
     * @return array{
     *   dry_run: bool,
     *   scanned: int,
     *   would_insert: int,
     *   inserted: int,
     *   skipped_existing: int,
     *   skipped_incomplete: int,
     *   conflicts: list<array{provider: string, provider_subject: string, user_ids: list<int>}>,
     *   aborted: bool,
     *   message: string
     * }
     */
    public function run(bool $dryRun = true, ?string $providerFilter = null): array
    {
        $query = User::query()
            ->whereNotNull('provider')
            ->where('provider', '!=', '')
            ->whereNotNull('provider_id')
            ->where('provider_id', '!=', '');

        if ($providerFilter) {
            $query->where('provider', OAuthProviders::normalize($providerFilter));
        }

        /** @var \Illuminate\Support\Collection<int, User> $rows */
        $rows = $query->get(['id', 'provider', 'provider_id', 'email']);

        $grouped = [];
        foreach ($rows as $user) {
            $provider = OAuthProviders::normalize((string) $user->provider);
            if (!OAuthProviders::isSupported($provider)) {
                continue;
            }
            $subject = trim((string) $user->provider_id);
            if ($subject === '') {
                continue;
            }
            $key = $provider.'|'.$subject;
            $grouped[$key][] = (int) $user->id;
        }

        $conflicts = [];
        foreach ($grouped as $key => $userIds) {
            $unique = array_values(array_unique($userIds));
            if (count($unique) > 1) {
                [$provider, $subject] = explode('|', $key, 2);
                $conflicts[] = [
                    'provider' => $provider,
                    'provider_subject' => $subject,
                    'user_ids' => $unique,
                ];
            }
        }

        if ($conflicts !== []) {
            return [
                'dry_run' => $dryRun,
                'scanned' => $rows->count(),
                'would_insert' => 0,
                'inserted' => 0,
                'skipped_existing' => 0,
                'skipped_incomplete' => 0,
                'conflicts' => $conflicts,
                'aborted' => true,
                'message' => 'Ambiguous provider_subject mappings — aborting without writes',
            ];
        }

        $wouldInsert = 0;
        $inserted = 0;
        $skippedExisting = 0;
        $skippedIncomplete = 0;

        foreach ($rows as $user) {
            $provider = OAuthProviders::normalize((string) $user->provider);
            $subject = trim((string) $user->provider_id);

            if (!OAuthProviders::isSupported($provider) || $subject === '') {
                $skippedIncomplete++;
                continue;
            }

            $exists = UserOAuthIdentity::query()
                ->where('provider', $provider)
                ->where('provider_subject', $subject)
                ->exists();

            if ($exists) {
                $skippedExisting++;
                continue;
            }

            // Also skip if this user already has a different subject for same provider.
            $other = UserOAuthIdentity::query()
                ->where('user_id', $user->id)
                ->where('provider', $provider)
                ->first();
            if ($other && $other->provider_subject !== $subject) {
                throw new RuntimeException(
                    "Conflict: user {$user->id} already linked to {$provider}/{$other->provider_subject}"
                );
            }
            if ($other) {
                $skippedExisting++;
                continue;
            }

            $wouldInsert++;
            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($user, $provider, $subject) {
                UserOAuthIdentity::create([
                    'user_id' => $user->id,
                    'provider' => $provider,
                    'provider_subject' => $subject,
                    'provider_email' => $user->email ? strtolower((string) $user->email) : null,
                    'provider_email_verified' => null,
                ]);
            });
            $inserted++;
        }

        return [
            'dry_run' => $dryRun,
            'scanned' => $rows->count(),
            'would_insert' => $wouldInsert,
            'inserted' => $inserted,
            'skipped_existing' => $skippedExisting,
            'skipped_incomplete' => $skippedIncomplete,
            'conflicts' => [],
            'aborted' => false,
            'message' => $dryRun ? 'Dry-run complete' : 'Backfill complete',
        ];
    }
}
