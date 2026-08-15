<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\Translatable\HasTranslations;

/**
 *
 *
 * @property int $id
 * @property array|null $title
 * @property array|null $text
 * @property string|null $images
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $color_title
 * @property string|null $color_text
 * @property string|null $images_banner
 * @property int $sorting
 * @property int $status
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BannerButton> $buttons
 * @property-read int|null $buttons_count
 * @property-read mixed $translations
 * @method static \Illuminate\Database\Eloquent\Builder|Banner newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Banner newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Banner query()
 * @method static \Illuminate\Database\Eloquent\Builder|Banner whereColorText($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Banner whereColorTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Banner whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Banner whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Banner whereImages($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Banner whereImagesBanner($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Banner whereJsonContainsLocale(string $column, string $locale, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|Banner whereJsonContainsLocales(string $column, array $locales, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|Banner whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder|Banner whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder|Banner whereSorting($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Banner whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Banner whereText($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Banner whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Banner whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Banner extends Model
{
    use HasTranslations;

    protected $table = 'banners';

    public $translatable = ['title', 'text'];

    protected $fillable = [
        'title',
        'text',
        'images',
        'color_text',
        'color_title',
        'images_banner',
        'sorting',
        'status',
    ];

    /**
     * Дополнительные поля для реквизитов
     */
    public function buttons(): MorphToMany
    {
        return $this->morphToMany(
            BannerButton::class,
            'model',
            'banners_has_banners_buttons',
            'model_id',
            'banners_button_id'
        );
    }
}
