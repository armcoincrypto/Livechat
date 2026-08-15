<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $id
 * @property string      $name
 * @property string      $slug
 * @property string|null $description
 * @property int         $max_num_order_user_hour
 * @property int         $max_num_order_user_day
 * @property int         $order_limit_count
 * @property int         $order_limit_minutes
 * @property bool        $is_default
 */
class SettingsLimitProfile extends Model
{
    use HasFactory;

    protected $table = 'settings_limit_profiles';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'max_num_order_user_hour',
        'max_num_order_user_day',
        'order_limit_count',
        'order_limit_minutes',
        'min_interval_between_orders_seconds',
        'first_orders_window_count',
        'first_orders_max_amount',
        'is_default',
    ];

    protected $casts = [
        'max_num_order_user_hour'             => 'int',
        'max_num_order_user_day'              => 'int',
        'order_limit_count'                   => 'int',
        'order_limit_minutes'                 => 'int',
        'min_interval_between_orders_seconds' => 'int',
        'first_orders_window_count'           => 'int',
        'first_orders_max_amount'             => 'string',
        'is_default'                          => 'bool',
    ];

    // --- Связи ---

    public function users()
    {
        return $this->hasMany(User::class, 'limit_profile_id');
    }

    public function directions()
    {
        return $this->hasMany(DirectionExchange::class, 'limit_profile_id');
    }

    // --- Скоупы / хелперы ---

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function isDefault(): bool
    {
        return (bool) $this->is_default;
    }

    /**
     * Приводим профиль к массиву лимитов для валидатора.
     */
    public function toLimitsArray(): array
    {
        return [
            'max_num_order_user_hour'             => (int) $this->max_num_order_user_hour,
            'max_num_order_user_day'              => (int) $this->max_num_order_user_day,
            'order_limit_count'                   => (int) $this->order_limit_count,
            'order_limit_minutes'                 => (int) $this->order_limit_minutes,
            'min_interval_between_orders_seconds' => (int) $this->min_interval_between_orders_seconds,
            'first_orders_window_count'           => (int) $this->first_orders_window_count,
            'first_orders_max_amount'             => $this->first_orders_max_amount
        ];
    }
}
