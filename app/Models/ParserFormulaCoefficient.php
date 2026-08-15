<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int $id
 * @property float $summa
 * @property string|null $name
 * @property string|null $alias
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|ParserFormulaCoefficient newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ParserFormulaCoefficient newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ParserFormulaCoefficient query()
 * @method static \Illuminate\Database\Eloquent\Builder|ParserFormulaCoefficient whereAlias($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ParserFormulaCoefficient whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ParserFormulaCoefficient whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ParserFormulaCoefficient whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ParserFormulaCoefficient whereSumma($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ParserFormulaCoefficient whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class ParserFormulaCoefficient extends Model
{
    protected $table = 'parser_formula_coefficient';

    protected $fillable = [
        'name',
        'summa',
        'alias',
        'template',
        'type_index',
        'comment'
    ];
}
