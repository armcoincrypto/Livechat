<?php

namespace App\Services\Administrator\Notify\Counters;

use App\Models\TaskMessage;
use App\Models\User;
use App\Services\Administrator\Notify\Contracts\AdminNotifyCounter;

final class OrderChatCounter implements AdminNotifyCounter
{
    public function key(): string
    {
        return 'count_chat';
    }

    public function allowed(User $user): bool
    {
        return $user->can('admin_tasks');
    }

    public function count(User $user): int
    {
        return (int) TaskMessage::query()
            ->where('type_user', 0)
            ->where('is_read', 0)
            ->count();
    }
}
