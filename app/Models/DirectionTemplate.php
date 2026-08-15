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
 * @property int $type_view_info
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $translations
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionTemplate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionTemplate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionTemplate query()
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionTemplate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionTemplate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionTemplate whereIdType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionTemplate whereJsonContainsLocale(string $column, string $locale, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionTemplate whereJsonContainsLocales(string $column, array $locales, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionTemplate whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionTemplate whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionTemplate whereText($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionTemplate whereTypeViewInfo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionTemplate whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class DirectionTemplate extends Model
{
    use HasTranslations;

    protected $table = 'direction_templates';

    protected $fillable = [
        'id_type',
        'text',
        'type_view_info',
    ];

    protected $translatable = [
        'text'
    ];
}
