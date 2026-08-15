<?php

namespace App\Models;

use App\Models\Filters\GatewayMerchantFilter;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\Translatable\HasTranslations;

class GatewayMerchant extends Model
{
    use Filterable, HasTranslations;

    protected $table = 'gateways_merchants';

    protected $fillable = [
        'name',
        'status',
        'description',
        'is_deny_ip_address',
        'allow_ip_address',
        'security_hash',
        'comment',
        'instruction_payment',
        'max_limit_amount_order',
        'day_limit_merchant',
        'amount_fault',
        'is_enable_merchant_button',
        'day_limit_amount_merchant',
        'total_usd',
        'last_order_id',
        'is_config_done',
        'pay_amount',
        'credit_amount',
        'order_num',
        'filename',
        'alias',
        'required_params',
        'ext_options',

        'status_invalid_min_amount',
        'status_invalid_max_amount',

        'month_limit_amount_merchant',
        'min_amount_for_per_order',
        'max_amount_for_per_order',
        'month_limit_merchant',
        'priority'
    ];

    protected array $translatable = [
        'instruction_payment',
        'comment',
    ];

    protected $casts = [
        'ext_options' => 'array'
    ];

    public function modelFilter()
    {
        return $this->provideFilter(GatewayMerchantFilter::class);
    }

    public function currencies(): MorphToMany
    {
        return $this->morphedByMany(Currency::class, 'model', 'currency_merchants');
    }

    public function direction_exchange(): MorphToMany
    {
        return $this->morphedByMany(DirectionExchange::class, 'model', 'direction_exchange_merchants');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'id_merchant', 'id');
    }

    public function healthStatus(): HasOne
    {
        return $this->hasOne(GatewayHealthStatus::class, 'entity_id', 'id')
            ->where('type', 'merchant');
    }
}
