<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int $id
 * @property int $id_task
 * @property int $id_field
 * @property string|null $field_name
 * @property string|null $field_value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $type_field
 * @property string|null $alias
 * @property-read \App\Models\RequisiteField|null $requisites_fields
 * @method static \Illuminate\Database\Eloquent\Builder|TaskField newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskField newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskField query()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskField whereAlias($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskField whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskField whereFieldName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskField whereFieldValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskField whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskField whereIdField($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskField whereIdTask($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskField whereTypeField($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskField whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class TaskField extends Model
{
    protected $table = 'tasks_fields';

    protected $fillable = [
        'id_task',
        'id_field',
        'field_key',
        'field_name',
        'field_value',
        'type_field',
        'alias',
    ];

    public function requisites_fields()
    {
        return $this->hasOne(RequisiteField::class, 'id', 'id_field');
    }
}
