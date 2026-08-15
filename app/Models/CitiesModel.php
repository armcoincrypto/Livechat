<?php

namespace App\Models;

use App\Models\Filters\CitiesModelFilter;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Translatable\HasTranslations;

/**
 *
 *
 * @property int $id
 * @property array|null $name
 * @property string|null $designation_xml
 * @property int $status
 * @property int $created_user_id
 * @property int $updated_user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DirectionExchangeCity> $direction_exchange_cities
 * @property-read int|null $direction_exchange_cities_count
 * @property-read mixed $translations
 * @method static \Illuminate\Database\Eloquent\Builder|CitiesModel filter(array $input = [], $filter = null)
 * @method static \Illuminate\Database\Eloquent\Builder|CitiesModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CitiesModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CitiesModel paginateFilter($perPage = null, $columns = [], $pageName = 'page', $page = null)
 * @method static \Illuminate\Database\Eloquent\Builder|CitiesModel query()
 * @method static \Illuminate\Database\Eloquent\Builder|CitiesModel simplePaginateFilter($perPage = null, $columns = [], $pageName = 'page', $page = null)
 * @method static \Illuminate\Database\Eloquent\Builder|CitiesModel whereBeginsWith($column, $value, $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|CitiesModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CitiesModel whereCreatedUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CitiesModel whereDesignationXml($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CitiesModel whereEndsWith($column, $value, $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|CitiesModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CitiesModel whereJsonContainsLocale(string $column, string $locale, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|CitiesModel whereJsonContainsLocales(string $column, array $locales, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|CitiesModel whereLike($column, $value, $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|CitiesModel whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder|CitiesModel whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder|CitiesModel whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CitiesModel whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CitiesModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CitiesModel whereUpdatedUserId($value)
 * @mixin \Eloquent
 */
class CitiesModel extends Model
{
    use Filterable, HasTranslations;

    protected $table = 'cities';

    protected $fillable = [
        'name',
        'status',
        'designation_xml',
        'country_id',
        'created_user_id',
        'updated_user_id',

    ];

    public $translatable = [
        'name',
    ];

    /**
     * Фильтры городов
     *
     * @return string|null
     */
    public function modelFilter(): ?string
    {
        return $this->provideFilter(CitiesModelFilter::class);
    }

    public function direction_exchange_cities()
    {
        return $this->hasMany(DirectionExchangeCity::class, 'city_id', 'id');
    }

    public function country(): HasOne
    {
        return $this->hasOne(GeoCountryList::class,'id','country_id');
    }
}
