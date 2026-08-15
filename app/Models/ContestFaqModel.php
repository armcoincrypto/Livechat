<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * 
 *
 * @property int $id
 * @property array|null $title
 * @property array|null $description
 * @property int $user_id
 * @property int $status
 * @property int $sorting
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $translations
 * @method static \Illuminate\Database\Eloquent\Builder|ContestFaqModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ContestFaqModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ContestFaqModel query()
 * @method static \Illuminate\Database\Eloquent\Builder|ContestFaqModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestFaqModel whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestFaqModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestFaqModel whereJsonContainsLocale(string $column, string $locale, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestFaqModel whereJsonContainsLocales(string $column, array $locales, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestFaqModel whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestFaqModel whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestFaqModel whereSorting($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestFaqModel whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestFaqModel whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestFaqModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestFaqModel whereUserId($value)
 * @mixin \Eloquent
 */
class ContestFaqModel extends Model
{
    use HasTranslations;

    protected $table = 'contests_faq';

    protected $fillable = [
        'title',
        'description',
        'user_id',
        'status',
        'sorting',
    ];

    protected $translatable = [
        'title', 'description',
    ];
}
