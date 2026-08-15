<?php

namespace App\Services\Administrator\Notify\Counters;

use App\Models\User;
use App\Models\VerificationCard;
use App\Services\Administrator\Notify\Contracts\AdminNotifyCounter;

final class VerificationCardCounter implements AdminNotifyCounter
{
    public function key(): string
    {
        return 'count_verifications';
    }

    public function allowed(User $user): bool
    {
        return $user->can('admin_verification_card');
    }

    public function count(User $user): int
    {
        return (int) VerificationCard::query()->where('status', 0)->count();
    }
}
