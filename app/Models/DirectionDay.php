<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $id_direction_exchange
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionDay newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionDay newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionDay query()
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionDay whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionDay whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionDay whereIdDirectionExchange($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionDay whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class DirectionDay extends Model
{
    protected $table = 'direction_day';

    protected $fillable = [
        'id_direction_exchange',
    ];
}
