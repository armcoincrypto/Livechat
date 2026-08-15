<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $id_direction_exchange
 * @property string|null $direction_name
 * @property string|null $description
 * @property string|null $exchange_rate
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\DirectionExchange|null $direction_exchange
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionExchangeMinPriceLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionExchangeMinPriceLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionExchangeMinPriceLog query()
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionExchangeMinPriceLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionExchangeMinPriceLog whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionExchangeMinPriceLog whereDirectionName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionExchangeMinPriceLog whereExchangeRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionExchangeMinPriceLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionExchangeMinPriceLog whereIdDirectionExchange($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionExchangeMinPriceLog whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class DirectionExchangeMinPriceLog extends Model
{
    protected $table = 'direction_exchange_min_price_logs';

    protected $fillable = [
        'id_direction_exchange',
        'direction_name',
        'description',
        'exchange_rate',
    ];

    public function direction_exchange()
    {
        return $this->hasOne(DirectionExchange::class, 'id', 'id_direction_exchange');
    }
}
