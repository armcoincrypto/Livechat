<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int $id
 * @property int $model_id
 * @property string $model_type
 * @property int $gateway_merchant_id
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyMerchant newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyMerchant newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyMerchant query()
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyMerchant whereGatewayMerchantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyMerchant whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyMerchant whereModelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyMerchant whereModelType($value)
 * @mixin \Eloquent
 */
class CurrencyMerchant extends Model
{
    protected $table = 'currency_merchants';

    protected $fillable = [
        'currency_id',
        'gateway_merchant_id',
        'currency_type',
        'network_code'
    ];
}
