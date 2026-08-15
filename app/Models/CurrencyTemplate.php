<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 *
 *
 * @property int $id
 * @property int $id_type
 * @property array|null $text
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $type_view_info
 * @property-read mixed $translations
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyTemplate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyTemplate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyTemplate query()
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyTemplate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyTemplate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyTemplate whereIdType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyTemplate whereJsonContainsLocale(string $column, string $locale, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyTemplate whereJsonContainsLocales(string $column, array $locales, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyTemplate whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyTemplate whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyTemplate whereText($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyTemplate whereTypeViewInfo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyTemplate whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CurrencyTemplate extends Model
{
    use HasTranslations;

    protected $table = 'currencies_templates';

    protected $fillable = [
        'id_type',
        'text',
        'type_view_info',
    ];

    protected $translatable = [
        'text',
    ];
}
