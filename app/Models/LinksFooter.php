<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 *
 *
 * @property int $id
 * @property array|null $name
 * @property array|null $url
 * @property int $id_group
 * @property int $sorting
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $is_blank
 * @property-read \App\Models\LinksFooterGroup|null $group
 * @property-read mixed $translations
 * @method static \Illuminate\Database\Eloquent\Builder|LinksFooter newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|LinksFooter newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|LinksFooter query()
 * @method static \Illuminate\Database\Eloquent\Builder|LinksFooter whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksFooter whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksFooter whereIdGroup($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksFooter whereIsBlank($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksFooter whereJsonContainsLocale(string $column, string $locale, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksFooter whereJsonContainsLocales(string $column, array $locales, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksFooter whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksFooter whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksFooter whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksFooter whereSorting($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksFooter whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksFooter whereUrl($value)
 * @mixin \Eloquent
 */
class LinksFooter extends Model
{
    use HasTranslations;

    protected $table = 'links_footers';

    protected $fillable = [
        'name',
        'url',
        'id_group',
        'is_blank',
        'status',
        'sorting',
    ];

    public $translatable = ['name', 'url'];

    public function group()
    {
        return $this->hasOne(LinksFooterGroup::class, 'id', 'id_group');
    }
}
