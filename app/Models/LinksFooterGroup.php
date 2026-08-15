<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 *
 *
 * @property int $id
 * @property int $sorting
 * @property array|null $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LinksFooter> $links
 * @property-read int|null $links_count
 * @property-read mixed $translations
 * @method static \Illuminate\Database\Eloquent\Builder|LinksFooterGroup newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|LinksFooterGroup newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|LinksFooterGroup query()
 * @method static \Illuminate\Database\Eloquent\Builder|LinksFooterGroup whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksFooterGroup whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksFooterGroup whereJsonContainsLocale(string $column, string $locale, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksFooterGroup whereJsonContainsLocales(string $column, array $locales, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksFooterGroup whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksFooterGroup whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksFooterGroup whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksFooterGroup whereSorting($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksFooterGroup whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LinksFooterGroup extends Model
{
    use HasTranslations;

    protected $table = 'links_footer_groups';

    protected $fillable = [
        'name',
        'sorting',
    ];

    public array $translatable = ['name'];

    public function links(): HasMany
    {
        return $this->hasMany(LinksFooter::class, 'id_group', 'id');
    }
}
