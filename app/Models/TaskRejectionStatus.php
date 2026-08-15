<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 *
 *
 * @property int $id
 * @property array $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $is_not_delete
 * @property-read mixed $translations
 * @method static \Illuminate\Database\Eloquent\Builder|TaskRejectionStatus newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskRejectionStatus newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskRejectionStatus query()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskRejectionStatus whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskRejectionStatus whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskRejectionStatus whereIsNotDelete($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskRejectionStatus whereJsonContainsLocale(string $column, string $locale, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskRejectionStatus whereJsonContainsLocales(string $column, array $locales, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskRejectionStatus whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskRejectionStatus whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskRejectionStatus whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskRejectionStatus whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class TaskRejectionStatus extends Model
{
    use HasTranslations;

    protected $table = 'tasks_rejection_status';

    protected $fillable = [
        'name',
        'is_not_delete',
    ];

    protected $translatable = [
        'name',
    ];
}
