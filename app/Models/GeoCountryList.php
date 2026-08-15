<?php
/**
 * Created by PhpStorm.
 * User: steei
 * Date: 10.11.2020
 * Time: 9:01
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 *
 *
 * @property int $id
 * @property string|null $code
 * @property array $value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $translations
 * @method static \Illuminate\Database\Eloquent\Builder|GeoCountryList newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GeoCountryList newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GeoCountryList query()
 * @method static \Illuminate\Database\Eloquent\Builder|GeoCountryList whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GeoCountryList whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GeoCountryList whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GeoCountryList whereJsonContainsLocale(string $column, string $locale, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|GeoCountryList whereJsonContainsLocales(string $column, array $locales, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|GeoCountryList whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder|GeoCountryList whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder|GeoCountryList whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GeoCountryList whereValue($value)
 * @mixin \Eloquent
 */
class GeoCountryList extends Model
{
    use HasTranslations;

    /**
     * The activity model uses the 'sessions' database.
     *
     * @var string
     */
    protected $table = 'geo_country_list';

    protected $fillable = [
        'code',
        'value',
    ];

    protected $translatable = [
        'value',
    ];
}
