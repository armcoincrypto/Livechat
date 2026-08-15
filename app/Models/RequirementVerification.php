<?php
/**
 * Created by PhpStorm.
 * User: steei
 * Date: 31.08.2019
 * Time: 8:34
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string|null $name
 * @property int $sorting
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|RequirementVerification newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|RequirementVerification newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|RequirementVerification query()
 * @method static \Illuminate\Database\Eloquent\Builder|RequirementVerification whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequirementVerification whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequirementVerification whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequirementVerification whereSorting($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequirementVerification whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class RequirementVerification extends Model
{
    protected $table = 'requirements_verification';

    protected $fillable = [
        'sorting',
        'name',
    ];
}
