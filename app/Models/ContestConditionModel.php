<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * 
 *
 * @property int $id
 * @property int $id_manager
 * @property int $sorting
 * @property array $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $translations
 * @method static \Illuminate\Database\Eloquent\Builder|ContestConditionModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ContestConditionModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ContestConditionModel query()
 * @method static \Illuminate\Database\Eloquent\Builder|ContestConditionModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestConditionModel whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestConditionModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestConditionModel whereIdManager($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestConditionModel whereJsonContainsLocale(string $column, string $locale, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestConditionModel whereJsonContainsLocales(string $column, array $locales, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestConditionModel whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestConditionModel whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestConditionModel whereSorting($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestConditionModel whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class ContestConditionModel extends Model
{
    use HasTranslations;

    protected $table = 'contests_conditions';

    protected $fillable = [
        'id_manager',
        'description',
        'sorting',
    ];

    protected array $translatable = [
        'description',
    ];
}
