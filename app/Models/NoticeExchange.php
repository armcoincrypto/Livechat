<?php

namespace App\Models;

use App\Models\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property array $text
 * @property int $status
 * @property int $color
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $link
 * @property int $sorting
 * @property int $is_enabled_schedule
 * @property string|null $from_time
 * @property string|null $to_time
 * @property int $is_blank
 * @property string|null $icon_notice
 * @property string|null $text_color
 * @property string|null $bg_color
 * @property string|null $text_size
 * @property-read mixed $translations
 * @method static \Illuminate\Database\Eloquent\Builder|NoticeExchange newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|NoticeExchange newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|NoticeExchange query()
 * @method static \Illuminate\Database\Eloquent\Builder|NoticeExchange whereBgColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder|NoticeExchange whereColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder|NoticeExchange whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|NoticeExchange whereFromTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|NoticeExchange whereIconNotice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|NoticeExchange whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|NoticeExchange whereIsBlank($value)
 * @method static \Illuminate\Database\Eloquent\Builder|NoticeExchange whereIsEnabledSchedule($value)
 * @method static \Illuminate\Database\Eloquent\Builder|NoticeExchange whereJsonContainsLocale(string $column, string $locale, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|NoticeExchange whereJsonContainsLocales(string $column, array $locales, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|NoticeExchange whereLink($value)
 * @method static \Illuminate\Database\Eloquent\Builder|NoticeExchange whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder|NoticeExchange whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder|NoticeExchange whereSorting($value)
 * @method static \Illuminate\Database\Eloquent\Builder|NoticeExchange whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|NoticeExchange whereText($value)
 * @method static \Illuminate\Database\Eloquent\Builder|NoticeExchange whereTextColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder|NoticeExchange whereTextSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder|NoticeExchange whereToTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|NoticeExchange whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class NoticeExchange extends Model
{
    use HasTranslations;

    protected $table = 'notices_exchange';

    protected $fillable = [
        'text',
        'status',
        'color',
        'link',
        'sorting',
        'is_enabled_schedule',
        'from_time',
        'to_time',
        'is_blank',
        'text_color',
        'bg_color',
        'icon_notice',
        'text_size',
    ];

    public $translatable = ['text'];
}
