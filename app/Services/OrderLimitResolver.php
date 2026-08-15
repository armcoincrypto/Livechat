<?php

namespace App\Services;

use App\Models\SettingsLimitProfile;
use App\Models\User;
use App\Models\DirectionExchange;

class OrderLimitResolver
{
    /**
     * Вернуть итоговые лимиты заявок для конкретного пользователя и направления.
     *
     * Формат ответа:
     * [
     *   'max_num_order_user_hour' => int,
     *   'max_num_order_user_day'  => int,
     *   'order_limit_count'       => int,
     *   'order_limit_minutes'     => int,
     * ]
     */
    public function resolve(?User $user, ?DirectionExchange $direction): array
    {
        $profile = $this->resolveProfile($user, $direction);

        if ($profile) {
            return [
                'max_num_order_user_hour'             => (int) $profile->max_num_order_user_hour,
                'max_num_order_user_day'              => (int) $profile->max_num_order_user_day,
                'order_limit_count'                   => (int) $profile->order_limit_count,
                'order_limit_minutes'                 => (int) $profile->order_limit_minutes,
                'min_interval_between_orders_seconds' => (int) $profile->min_interval_between_orders_seconds,
                'first_orders_window_count'           => (int) $profile->first_orders_window_count,
                'first_orders_max_amount'             => $profile->first_orders_max_amount, // string|null
            ];
        }

        // Нет профиля — все лимиты выключены
        return [
            'max_num_order_user_hour'             => 0,
            'max_num_order_user_day'              => 0,
            'order_limit_count'                   => 0,
            'order_limit_minutes'                 => 0,
            'min_interval_between_orders_seconds' => 0,
            'first_orders_window_count'           => 0,
            'first_orders_max_amount'             => null,
        ];
    }

    /**
     * Логика выбора профиля.
     *
     * Приоритет:
     *  1. Профиль, назначенный направлению обмена
     *  2. Профиль, назначенный пользователю
     *  3. Профиль по умолчанию
     */
    public function resolveProfile(?User $user, ?DirectionExchange $direction): ?SettingsLimitProfile
    {
        // 1. Приоритет — профиль направления
        if ($direction && !empty($direction->limit_profile_id)) {
            $profile = SettingsLimitProfile::find($direction->limit_profile_id);
            if ($profile) {
                return $profile;
            }
        }

        // 2. Профиль пользователя
        if ($user && !empty($user->limit_profile_id)) {
            $profile = SettingsLimitProfile::find($user->limit_profile_id);
            if ($profile) {
                return $profile;
            }
        }

        // 3. Профиль по умолчанию
        return SettingsLimitProfile::where('is_default', true)->first();
    }
}
