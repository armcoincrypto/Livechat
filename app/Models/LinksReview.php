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
 * @property int $sorting
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $icon
 * @property int $is_review
 * @property string|null $type
 * @property array|null $description
 * @property int $id_group
 * @property int $is_bot
 * @property int $count_review
 * @property-read \App\Models\LinksReviewGroup|null $group
 * @property-read mixed $translations
 * @method static \Illuminate\Database\Eloquent\Builder|LinksReview newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|LinksReview newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|LinksReview query()
 * @method static \Illuminate\Database\Eloquent\Builder|LinksReview whereCountReview($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksReview whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksReview whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksReview whereIcon($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksReview whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksReview whereIdGroup($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksReview whereIsBot($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksReview whereIsReview($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksReview whereJsonContainsLocale(string $column, string $locale, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksReview whereJsonContainsLocales(string $column, array $locales, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksReview whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksReview whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksReview whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksReview whereSorting($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksReview whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksReview whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinksReview whereUrl($value)
 * @mixin \Eloquent
 */
class LinksReview extends Model
{
    use HasTranslations;

    protected $table = 'links_reviews';

    protected $fillable = [
        'name',
        'url',
        'type',
        'icon',
        'is_review',
        'id_group',
        'sorting',
        'description',
        'is_show',
        'is_bot',
        'count_review',
    ];

    public $translatable = ['name', 'description', 'url'];

    public function group()
    {
        return $this->hasOne(LinksReviewGroup::class, 'id', 'id_group');
    }
}
