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
 * @property int $min_char
 * @property int $max_char
 * @property int $obligatory_field
 * @property int $status
 * @property string|null $key_id
 * @property string|null $field_type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property array|null $description
 * @property int $remove_spaces
 * @property int $sorting
 * @property int $language_field
 * @property string|null $start_with
 * @property string|null $end_with
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DirectionExchange> $direction_exchange
 * @property-read int|null $direction_exchange_count
 * @property-read mixed $translations
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionField newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionField newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionField query()
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionField whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionField whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionField whereEndWith($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionField whereFieldType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionField whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionField whereJsonContainsLocale(string $column, string $locale, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionField whereJsonContainsLocales(string $column, array $locales, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionField whereKeyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionField whereLanguageField($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionField whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionField whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionField whereMaxChar($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionField whereMinChar($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionField whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionField whereObligatoryField($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionField whereRemoveSpaces($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionField whereSorting($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionField whereStartWith($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionField whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionField whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class DirectionField extends Model
{
    use HasTranslations;

    protected $table = 'directions_fields';

    protected $fillable = [
        'name',
        'min_char',
        'max_char',
        'obligatory_field',
        'status',
        'key_id',
        'field_type',
        'remove_spaces',
        'description',
        'sorting',
        'language_field',
        'start_with',
        'end_with',
    ];

    public $translatable = ['name', 'description'];

    public function direction_exchange(): MorphToMany
    {
        return $this->morphedByMany(DirectionExchange::class, 'model', 'directions_has_fields', 'direction_field_id');
    }
}
