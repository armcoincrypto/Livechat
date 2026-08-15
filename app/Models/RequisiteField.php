<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\Translatable\HasTranslations;

/**
 * 
 *
 * @property int $id
 * @property array|null $name
 * @property string|null $value
 * @property int $status
 * @property int $sorting
 * @property int $id_currency
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property array|null $comment
 * @property string|null $prefix
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Currency> $currencies
 * @property-read int|null $currencies_count
 * @property-read mixed $translations
 * @method static \Illuminate\Database\Eloquent\Builder|RequisiteField newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|RequisiteField newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|RequisiteField query()
 * @method static \Illuminate\Database\Eloquent\Builder|RequisiteField whereComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequisiteField whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequisiteField whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequisiteField whereIdCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequisiteField whereJsonContainsLocale(string $column, string $locale, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequisiteField whereJsonContainsLocales(string $column, array $locales, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequisiteField whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder|RequisiteField whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder|RequisiteField whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequisiteField wherePrefix($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequisiteField whereSorting($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequisiteField whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequisiteField whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequisiteField whereValue($value)
 * @mixin \Eloquent
 */
class RequisiteField extends Model
{
    use HasTranslations;

    protected $table = 'requisites_fields';

    public $translatable = ['comment', 'name'];

    protected $fillable = [
        'name',
        'value',
        'status',
        'sorting',
        'id_currency',
        'comment',
        'prefix',
    ];

    public function currencies(): MorphToMany
    {
        return $this->morphedByMany(Currency::class, 'model', 'currency_requisites_has_fields', 'field_id');
    }
}
