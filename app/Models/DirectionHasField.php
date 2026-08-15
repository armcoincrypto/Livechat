<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $model_id
 * @property string $model_type
 * @property int $direction_field_id
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionHasField newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionHasField newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionHasField query()
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionHasField whereDirectionFieldId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionHasField whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionHasField whereModelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionHasField whereModelType($value)
 * @mixin \Eloquent
 */
class DirectionHasField extends Model
{
    protected $table = 'directions_has_fields';

    protected $fillable = [
        'model_id',
        'model_type',
        'direction_field_id',
    ];
}
