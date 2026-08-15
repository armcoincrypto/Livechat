<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $id_bestchange
 * @property int $status
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $id_direction_exchange
 * @property-read \App\Models\DirectionExchange|null $direction_exchange
 * @method static \Illuminate\Database\Eloquent\Builder|BestchangeParserError newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|BestchangeParserError newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|BestchangeParserError query()
 * @method static \Illuminate\Database\Eloquent\Builder|BestchangeParserError whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BestchangeParserError whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BestchangeParserError whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BestchangeParserError whereIdBestchange($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BestchangeParserError whereIdDirectionExchange($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BestchangeParserError whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BestchangeParserError whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class BestchangeParserError extends Model
{
    protected $table = 'bestchange_parser_error';

    protected $fillable = [
        'id_bestchange',
        'id_direction_exchange',
        'status',
        'description',
    ];

    public function direction_exchange()
    {
        return $this->hasOne(DirectionExchange::class, 'id', 'id_direction_exchange');
    }
}
