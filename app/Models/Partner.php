<?php

namespace App\Models;

use App\Models\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property array|null $name
 * @property array|null $link
 * @property string|null $logo
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $sorting
 * @property-read mixed $translations
 * @method static \Illuminate\Database\Eloquent\Builder|Partner newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Partner newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Partner query()
 * @method static \Illuminate\Database\Eloquent\Builder|Partner whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Partner whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Partner whereJsonContainsLocale(string $column, string $locale, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|Partner whereJsonContainsLocales(string $column, array $locales, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|Partner whereLink($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Partner whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder|Partner whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder|Partner whereLogo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Partner whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Partner whereSorting($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Partner whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Partner extends Model
{
    use HasTranslations;

    protected $table = 'partners';

    public $translatable = ['name', 'link'];

    protected $fillable = [
        'name',
        'logo',
        'link',
        'sorting',
    ];
}
