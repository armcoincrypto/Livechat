<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\TelegramNotification;
use Illuminate\Support\Collection;

/**
 * Resolves enabled operator Telegram channels for an event key.
 *
 * Admin stores flags as is_process_created_order; some dispatchers pass
 * process_created_order. Both must match.
 */
final class TelegramNotificationSelector
{
    /**
     * @return list<string>
     */
    public static function eventKeys(string $param): array
    {
        $param = trim($param);
        if ($param === '') {
            return [];
        }

        $keys = [$param];
        if (str_starts_with($param, 'is_')) {
            $keys[] = substr($param, 3);
        } else {
            $keys[] = 'is_'.$param;
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return Collection<int, TelegramNotification>
     */
    public function enabledForEvent(string $param): Collection
    {
        $keys = self::eventKeys($param);
        if ($keys === []) {
            return collect();
        }

        return TelegramNotification::query()
            ->where('status', 1)
            ->where(function ($query) use ($keys): void {
                foreach ($keys as $key) {
                    $query->orWhere('ext_params->'.$key, 1);
                }
            })
            ->get();
    }
}
