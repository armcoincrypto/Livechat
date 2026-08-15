<?php

namespace App\Services\Administrator\Notify;

use App\Models\User;
use App\Services\Administrator\Notify\Contracts\AdminNotifyCounter;

final class AdminNotifyCountsService
{
    /**
     * @param array<int,AdminNotifyCounter> $counters
     */
    public function __construct(
        private readonly array $counters
    ) {}

    public function build(?User $user): array
    {
        $counts = [
            'count_orders' => [
                'total' => 0,
                'live_orders' => 0,
                'frozen_counts' => 0,
                'withdrawal_counts' => 0,
            ],
            'count_chat' => 0,
            'count_notification_event' => 0,
            'count_verifications' => 0,
            'count_user_verify' => 0,
            'count_review' => 0,
        ];

        if (!$user) {
            return $counts;
        }

        foreach ($this->counters as $counter) {
            if (!$counter->allowed($user)) {
                continue;
            }

            $value = $counter->count($user);
            $key = $counter->key();

            if (is_array($value)) {
                $counts[$key] = array_replace_recursive($counts[$key] ?? [], $value);
            } else {
                $counts[$key] = (int) $value;
            }
        }

        return $counts;
    }
}
