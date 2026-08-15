<?php

namespace App\Services\Administrator\Notify\Counters;

use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\Administrator\Notify\Contracts\AdminNotifyCounter;

final class WithdrawalRequestsCounter implements AdminNotifyCounter
{
    public function key(): string
    {
        return 'count_orders';
    }

    public function allowed(User $user): bool
    {
        return $user->can('claims_payment');
    }

    public function count(User $user): array
    {
        return [
            'withdrawal_counts' => (int) WithdrawalRequest::query()
                ->where('status', 0)
                ->count(),
        ];
    }
}
