<?php

namespace App\Services\Administrator\Notify\Counters;

use App\Models\Task;
use App\Models\User;
use App\Services\Administrator\Notify\Contracts\AdminNotifyCounter;

final class OrdersCounter implements AdminNotifyCounter
{
    public function key(): string
    {
        return 'count_orders';
    }

    public function allowed(User $user): bool
    {
        return $user->can('admin_tasks');
    }

    public function count(User $user): array
    {
        $liveStatus = array_filter(array_map(
            'trim',
            explode(',', (string) iEXSetting('iex_order_live_statuses'))
        ));

        // Важно: with() для count() не нужен — лишняя нагрузка.
        $liveQuery = Task::query()
            ->when(!empty($liveStatus), fn($q) => $q->whereIn('status', $liveStatus));

        if ((int) iEXSetting('iex_order_live_is_request_payment', 0) === 1) {
            $liveQuery->orWhere(function ($q) {
                $q->where('status', 2)
                    ->where('is_request_payment_type', 1);
            });
        }

        if ((int) iEXSetting('iex_order_is_hidden_order_opened') === 1) {
            $liveQuery->where(function ($query) use ($user) {
                $query->whereDoesntHave('task_operators')
                    ->orWhereHas('task_operators', function ($q) use ($user) {
                        $q->where('id_user', (int) $user->id);
                    });
            });
        }

        return [
            'total' => (int) Task::query()->count(),
            'live_orders' => (int) $liveQuery->count(),
            'frozen_counts' => (int) Task::query()->where('status', 8)->count(),
            'withdrawal_counts' => 0, // отдельный counter по праву claims_payment
        ];
    }
}
