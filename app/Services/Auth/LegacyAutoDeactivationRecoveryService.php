<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\HistoryInternalAccount;
use App\Models\InternalAccount;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Classify and safely reactivate accounts deactivated by the old
 * unverified auto-cleanup rule (monitoring:daily deactivateUnverifiedUsers).
 *
 * Never changes passwords, email_verified_at, KYC, bans, balances, or orders.
 */
final class LegacyAutoDeactivationRecoveryService
{
    public const VERSION = 'legacy-recovery-v1';

    public const ARTIFACT_DIR = '/var/lib/exswaping-owned/legacy-account-recovery';

    /**
     * @return array{
     *   A: list<int>,
     *   B: list<int>,
     *   C: list<int>,
     *   D: list<int>,
     *   meta: array<string,mixed>
     * }
     */
    public function classify(int $cleanupDays): array
    {
        $funded = UserAccountLifecycleService::FUNDED_OR_COMPLETED_TASK_STATUSES;

        $base = User::query()
            ->whereNull('email_verified_at')
            ->where('deactivation', 1)
            ->where('is_guest', 0)
            ->doesntHave('roles');

        $ids = $base->pluck('id')->all();
        $orderUsers = array_flip(
            DB::table('tasks')->whereIn('id_user', $ids)->distinct()->pluck('id_user')->all()
        );
        $fundedUsers = array_flip(
            DB::table('tasks')->whereIn('id_user', $ids)->whereIn('status', $funded)->distinct()->pluck('id_user')->all()
        );
        $finUsers = array_flip(
            DB::table('internal_accounts')->whereIn('id_user', $ids)->where('balance', '>', 0)->distinct()->pluck('id_user')->all()
        );
        $histFin = array_flip(
            DB::table('history_internal_accounts')->whereIn('id_user', $ids)->distinct()->pluck('id_user')->all()
        );

        $A = [];
        $B = [];
        $C = [];
        $D = [];
        $originAuto = 0;
        $originSecurity = 0;
        $originUnknown = 0;

        foreach ($ids as $id) {
            $u = DB::table('users')->where('id', $id)->first([
                'id', 'created_at', 'last_login_at', 'num_auth', 'banned_at', 'is_guest', 'email_verified_at',
            ]);

            $banned = ! empty($u->banned_at);
            $hasOrder = isset($orderUsers[$id]);
            $hasFunded = isset($fundedUsers[$id]) || isset($finUsers[$id]) || isset($histFin[$id]);
            $hasLoginAfterCreate = $u->last_login_at && $u->last_login_at > $u->created_at;
            $hasNumAuth = (int) $u->num_auth > 0;
            $hasMeaningful = $hasOrder || $hasLoginAfterCreate || $hasNumAuth || isset($finUsers[$id]) || isset($histFin[$id]);

            $oldUnverifiedFingerprint = is_null($u->email_verified_at)
                && strtotime((string) $u->created_at) < strtotime("-{$cleanupDays} days")
                && (int) $u->is_guest === 0
                && ! $banned;

            if ($banned) {
                $D[] = (int) $id;
                $originSecurity++;
                continue;
            }

            if (! $oldUnverifiedFingerprint) {
                $D[] = (int) $id;
                $originUnknown++;
                continue;
            }

            $originAuto++;

            if (! $hasMeaningful) {
                $A[] = (int) $id;
                continue;
            }

            if ($hasFunded) {
                $C[] = (int) $id;
            } else {
                $B[] = (int) $id;
            }
        }

        return [
            'A' => $A,
            'B' => $B,
            'C' => $C,
            'D' => $D,
            'meta' => [
                'cleanup_days' => $cleanupDays,
                'origin_auto' => $originAuto,
                'origin_security' => $originSecurity,
                'origin_unknown' => $originUnknown,
                'version' => self::VERSION,
            ],
        ];
    }

    /**
     * Eligible for legacy reactivation: Cohort B or C only.
     */
    public function isEligibleForReactivation(User $user, string $cohort, int $cleanupDays): bool
    {
        if (! in_array($cohort, ['B', 'C'], true)) {
            return false;
        }
        if ((int) $user->deactivation !== 1) {
            return false;
        }
        if ($user->banned_at !== null) {
            return false;
        }
        if ($user->email_verified_at !== null) {
            // Outside the historical unverified auto-cleanup fingerprint.
            return false;
        }
        if ((int) $user->is_guest === 1) {
            return false;
        }
        if ($user->roles()->exists()) {
            return false;
        }
        if ($user->created_at->gt(now()->subDays($cleanupDays))) {
            return false;
        }

        $lifecycle = app(UserAccountLifecycleService::class);
        if (! $lifecycle->hasMeaningfulCustomerHistory($user)) {
            return false;
        }

        $hasFunded = $lifecycle->hasFundedOrCompletedOrderHistory($user)
            || InternalAccount::query()->where('id_user', $user->id)->where('balance', '>', 0)->exists()
            || HistoryInternalAccount::query()->where('id_user', $user->id)->exists();

        if ($cohort === 'C') {
            return $hasFunded;
        }

        // Cohort B: meaningful but not funded/financial.
        return ! $hasFunded;
    }

    /**
     * @param  list<int>  $ids
     * @return array{reactivated: list<array<string,mixed>>, skipped: list<array<string,mixed>>}
     */
    public function reactivateIds(array $ids, string $cohort, int $cleanupDays, bool $apply): array
    {
        $reactivated = [];
        $skipped = [];

        foreach ($ids as $id) {
            /** @var User|null $user */
            $user = User::query()->find($id);
            if (! $user) {
                $skipped[] = ['user_id' => $id, 'reason' => 'missing'];
                continue;
            }

            if (! $this->isEligibleForReactivation($user, $cohort, $cleanupDays)) {
                $skipped[] = [
                    'user_id' => $id,
                    'reason' => 'not_eligible',
                    'deactivation' => (int) $user->deactivation,
                    'banned' => $user->banned_at !== null,
                ];
                continue;
            }

            $before = (int) $user->deactivation;
            $verifiedBefore = $user->email_verified_at !== null;

            if ($apply) {
                // Only flip deactivation. Do not touch email_verified_at / password / KYC.
                $user->forceFill(['deactivation' => 0])->save();
                $user->refresh();
            }

            $reactivated[] = [
                'user_id' => (int) $user->id,
                'cohort' => $cohort,
                'why_eligible' => $cohort === 'C' ? 'funded_or_financial_history' : 'login_or_order_history',
                'deactivation_before' => $before,
                'deactivation_after' => $apply ? (int) $user->deactivation : 0,
                'email_verified_changed' => false,
                'email_verified_before' => $verifiedBefore,
                'timestamp' => gmdate('c'),
                'recovery_command_version' => self::VERSION,
                'applied' => $apply,
            ];
        }

        return compact('reactivated', 'skipped');
    }

    public function writeClassificationArtifacts(array $classified): string
    {
        $dir = self::ARTIFACT_DIR;
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        foreach (['A', 'B', 'C', 'D'] as $key) {
            $path = "{$dir}/cohort_{$key}_ids.json";
            file_put_contents($path, json_encode(array_values($classified[$key]), JSON_PRETTY_PRINT));
            @chmod($path, 0640);
        }

        $metaPath = "{$dir}/classification_meta.json";
        file_put_contents($metaPath, json_encode([
            'generated_at' => gmdate('c'),
            'counts' => [
                'A' => count($classified['A']),
                'B' => count($classified['B']),
                'C' => count($classified['C']),
                'D' => count($classified['D']),
                'total' => count($classified['A']) + count($classified['B']) + count($classified['C']) + count($classified['D']),
            ],
            'meta' => $classified['meta'],
        ], JSON_PRETTY_PRINT));
        @chmod($metaPath, 0640);

        return $dir;
    }
}
