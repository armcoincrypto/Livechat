<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 *
 *
 * @property int $id
 * @property array $name
 * @property string|null $color
 * @property string|null $class
 * @property int $is_export
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $allow_delete
 * @property int $sorting
 * @property-read mixed $translations
 * @method static \Illuminate\Database\Eloquent\Builder|TaskStatus newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskStatus newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskStatus query()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskStatus whereAllowDelete($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskStatus whereClass($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskStatus whereColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskStatus whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskStatus whereIsExport($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskStatus whereJsonContainsLocale(string $column, string $locale, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskStatus whereJsonContainsLocales(string $column, array $locales, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskStatus whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskStatus whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskStatus whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskStatus whereSorting($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskStatus whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class TaskStatus extends Model
{
    use HasTranslations;

    public $timestamps = false;


    protected $table = 'tasks_status';

    protected $fillable = [
        'name',
        'is_export',
        'class',
        'color',
        'sorting',
    ];

    protected array $translatable = [
        'name',
    ];

    public function tasks() {
        return $this->hasMany(Task::class,'status', 'id');
    }
}
