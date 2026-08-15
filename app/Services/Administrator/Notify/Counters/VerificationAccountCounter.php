<?php

namespace App\Services\Administrator\Notify\Counters;

use App\Models\User;
use App\Models\UserVerification;
use App\Services\Administrator\Notify\Contracts\AdminNotifyCounter;

final class VerificationAccountCounter implements AdminNotifyCounter
{
    public function key(): string
    {
        return 'count_user_verify';
    }

    public function allowed(User $user): bool
    {
        return $user->can('admin_verification_account');
    }

    public function count(User $user): int
    {
        return (int) UserVerification::query()->where('status', 0)->count();
    }
}
