<?php

namespace App\Services\Administrator\Notify\Counters;

use App\Models\NotificationEvent;
use App\Models\User;
use App\Services\Administrator\Notify\Contracts\AdminNotifyCounter;

final class NotificationEventsCounter implements AdminNotifyCounter
{
    public function key(): string
    {
        return 'count_notification_event';
    }

    public function allowed(User $user): bool
    {
        return $user->can('admin_tasks');
    }

    public function count(User $user): int
    {
        return (int) NotificationEvent::query()
            ->where('is_read', 0)
            ->count();
    }
}
