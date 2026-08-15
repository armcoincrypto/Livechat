<?php

namespace App\Services\Administrator\Notify\Counters;

use App\Models\Review;
use App\Models\User;
use App\Services\Administrator\Notify\Contracts\AdminNotifyCounter;

final class ReviewsCounter implements AdminNotifyCounter
{
    public function key(): string
    {
        return 'count_review';
    }

    public function allowed(User $user): bool
    {
        return $user->can('admin_reviews');
    }

    public function count(User $user): int
    {
        return (int) Review::query()->where('status', 0)->count();
    }
}
