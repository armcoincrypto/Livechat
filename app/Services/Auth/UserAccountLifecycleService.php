<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\HistoryInternalAccount;
use App\Models\InternalAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Account lifecycle helpers for unverified cleanup and password-reset recovery.
 *
 * Does not touch KYC / verification-of-identity fields — only mailbox ownership
 * (email_verified_at) and the product deactivation flag.
 */
final class UserAccountLifecycleService
{
    /**
     * Statuses that indicate funded / completed / in-settlement order history.
     * 3 = awaiting processing, 4 = completed, 7 = paid, plus settlement family.
     */
    public const FUNDED_OR_COMPLETED_TASK_STATUSES = [3, 4, 7, 8, 9, 12, 13, 14, 15, 16];

    /**
     * True when the account has real customer activity that must not be
     * auto-deactivated solely for missing email_verified_at.
     */
    public function hasMeaningfulCustomerHistory(User $user): bool
    {
        if ((int) $user->num_auth > 0) {
            return true;
        }

        if (
            $user->last_login_at
            && $user->created_at
            && $user->last_login_at->gt($user->created_at)
        ) {
            return true;
        }

        if ($user->tasks()->exists()) {
            return true;
        }

        if (
            InternalAccount::query()
                ->where('id_user', $user->id)
                ->where('balance', '>', 0)
                ->exists()
        ) {
            return true;
        }

        if (
            HistoryInternalAccount::query()
                ->where('id_user', $user->id)
                ->exists()
        ) {
            return true;
        }

        return false;
    }

    public function hasFundedOrCompletedOrderHistory(User $user): bool
    {
        return $user->tasks()
            ->whereIn('status', self::FUNDED_OR_COMPLETED_TASK_STATUSES)
            ->exists();
    }

    /**
     * Restrict a users query to accounts eligible for unverified auto-deactivation.
     */
    public function scopeEligibleForUnverifiedAutoDeactivation(Builder $query, int $days, ?Carbon $now = null): Builder
    {
        $now ??= Carbon::now();
        $threshold = $now->copy()->subDays($days);

        return $query
            ->whereNull('email_verified_at')
            ->where('created_at', '<', $threshold)
            ->where('deactivation', '!=', 1)
            ->doesntHave('roles')
            ->where('is_guest', 0)
            ->where('num_auth', 0)
            ->where(function (Builder $login) {
                $login->whereNull('last_login_at')
                    ->orWhereColumn('last_login_at', '<=', 'created_at');
            })
            ->whereDoesntHave('tasks')
            ->whereDoesntHave('internalAccounts', function (Builder $accounts) {
                $accounts->where('balance', '>', 0);
            })
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('history_internal_accounts')
                    ->whereColumn('history_internal_accounts.id_user', 'users.id');
            });
    }

    /**
     * Password-reset link proves mailbox control. Mark email verified and clear
     * stale product deactivation so recovery is not permanently blocked.
     * Does not alter KYC / is_verification / is_verify_account.
     */
    public function recoverAfterSuccessfulPasswordReset(User $user): void
    {
        if (! $user->hasVerifiedEmail()) {
            // Persists email_verified_at via MustVerifyEmail.
            $user->markEmailAsVerified();
        }

        if ((int) $user->deactivation === 1) {
            $user->forceFill(['deactivation' => 0])->save();
        }
    }
}
