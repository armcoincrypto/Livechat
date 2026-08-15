<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string|null $name
 * @property string|null $value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|ParserType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ParserType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ParserType query()
 * @method static \Illuminate\Database\Eloquent\Builder|ParserType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ParserType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ParserType whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ParserType whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ParserType whereValue($value)
 * @mixin \Eloquent
 */
class ParserType extends Model
{
    protected $table = 'parser_type';
}
