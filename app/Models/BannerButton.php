<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 *
 *
 * @property int $id
 * @property array|null $name
 * @property array|null $link
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $color_text_button
 * @property string|null $color_bg_button
 * @property-read mixed $translations
 * @method static \Illuminate\Database\Eloquent\Builder|BannerButton newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|BannerButton newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|BannerButton query()
 * @method static \Illuminate\Database\Eloquent\Builder|BannerButton whereColorBgButton($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BannerButton whereColorTextButton($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BannerButton whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BannerButton whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BannerButton whereJsonContainsLocale(string $column, string $locale, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|BannerButton whereJsonContainsLocales(string $column, array $locales, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|BannerButton whereLink($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BannerButton whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder|BannerButton whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder|BannerButton whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BannerButton whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class BannerButton extends Model
{
    use HasTranslations;

    protected $table = 'banners_buttons';

    public $translatable = ['name', 'link'];

    protected $fillable = [
        'name',
        'link',
        'color_text_button',
        'color_bg_button',
    ];
}
