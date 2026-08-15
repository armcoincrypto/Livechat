<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string|null $name
 * @property int $status
 * @property string|null $from_limit
 * @property string|null $to_limit
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OperationLevel> $operation_level
 * @property-read int|null $operation_level_count
 * @method static \Illuminate\Database\Eloquent\Builder|OperatorLevelGroup newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|OperatorLevelGroup newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|OperatorLevelGroup query()
 * @method static \Illuminate\Database\Eloquent\Builder|OperatorLevelGroup whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OperatorLevelGroup whereFromLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OperatorLevelGroup whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OperatorLevelGroup whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OperatorLevelGroup whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OperatorLevelGroup whereToLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OperatorLevelGroup whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class OperatorLevelGroup extends Model
{
    protected $table = 'operation_level_groups';

    protected $fillable = [
        'name',
        'status',
        'from_limit',
        'to_limit',
    ];

    public function operation_level()
    {
        return $this->hasMany(OperationLevel::class, 'id_level_group', 'id');
    }
}
