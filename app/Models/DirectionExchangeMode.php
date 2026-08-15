<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * 
 *
 * @property int $id
 * @property string|null $name
 * @property int $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DirectionExchange> $direction_exchange
 * @property-read int|null $direction_exchange_count
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionExchangeMode newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionExchangeMode newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionExchangeMode query()
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionExchangeMode whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionExchangeMode whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionExchangeMode whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionExchangeMode whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionExchangeMode whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class DirectionExchangeMode extends Model
{
    protected $table = 'direction_exchange_modes';

    protected $fillable = [
        'name',
        'status',
    ];

    public function direction_exchange(): MorphToMany
    {
        return $this->morphedByMany(DirectionExchange::class, 'model', 'directions_has_modes');
    }
}
