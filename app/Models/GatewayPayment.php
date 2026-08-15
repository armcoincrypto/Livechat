<?php

namespace App\Models;

use App\Models\Filters\GatewayPaymentFilter;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\Translatable\HasTranslations;

class GatewayPayment extends Model
{
    use HasTranslations;
    use Filterable;

    protected $table = 'gateways_payments';

    protected $fillable = [
        'name',
        'status',
        'id_proxy',
        'comment',
        'manual_pay_order',
        'volume_to_usd',
        'last_order_id',
        'order_count',
        'pay_amount_type',
        'direction',
        'alias',
        'ext_options',
        'filename',

        'day_limit_amount_pay',
        'month_limit_amount_pay',
        'min_amount_for_per_order',
        'max_amount_for_per_order',
        'day_limit_pay',
        'month_limit_pay',
        'allow_autopay'
    ];

    protected array $translatable = [
        'comment',
    ];

    protected $casts = [
        'ext_options' => 'array'
    ];


    /**
     * Фильтры
     *
     * @return string|null
     */
    public function modelFilter(): ?string
    {
        return $this->provideFilter(GatewayPaymentFilter::class);
    }

    public function currencies(): MorphToMany
    {
        return $this->morphedByMany(Currency::class, 'model', 'currency_payments');
    }

    public function direction_exchange(): MorphToMany
    {
        return $this->morphedByMany(DirectionExchange::class, 'model', 'direction_exchange_pay', 'gateway_pay_id');
    }


    public function proxy()
    {
        return $this->hasOne(ProxyModel::class, 'id', 'id_proxy');
    }
}
