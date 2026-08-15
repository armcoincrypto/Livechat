<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 *
 *
 * @property int $id
 * @property array|null $name
 * @property int $sorting
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LinksReview> $links_review
 * @property-read int|null $links_review_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LinksReview> $links_review_large
 * @property-read int|null $links_review_large_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LinksReview> $links_review_url
 * @property-read int|null $links_review_url_count
 * @property-read mixed $translations
 * @method static \Illuminate\Database\Eloquent\Builder|LinksReviewGroup newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|LinksReviewGroup newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|LinksReviewGroup query()
 * @method static \Illuminate\Database\Eloquent\Builder|LinksReviewGroup whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksReviewGroup whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksReviewGroup whereJsonContainsLocale(string $column, string $locale, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksReviewGroup whereJsonContainsLocales(string $column, array $locales, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksReviewGroup whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksReviewGroup whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksReviewGroup whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksReviewGroup whereSorting($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksReviewGroup whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LinksReviewGroup extends Model
{
    use HasTranslations;

    protected $table = 'links_review_groups';

    protected $fillable = [
        'name',
        'sorting',
    ];

    public array $translatable = ['name'];

    public function links_review(): HasMany
    {
        return $this->hasMany(LinksReview::class, 'id_group', 'id');
    }

    public function links_review_large(): HasMany
    {
        return $this->hasMany(LinksReview::class, 'id_group', 'id')->where('is_review', '=', 1)->orderBy('sorting');
    }

    public function links_review_url(): HasMany
    {
        return $this->hasMany(LinksReview::class, 'id_group', 'id')->where('is_review', '=', 0)->orderBy('sorting');
    }
}
