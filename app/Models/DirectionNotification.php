<?php
/**
 * Created by PhpStorm.
 * User: steei
 * Date: 23.06.2019
 * Time: 12:26
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 *
 *
 * @property int $id
 * @property int $id_direction_exchange
 * @property array|null $description
 * @property int $sorting
 * @property int $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $is_enabled_schedule
 * @property string|null $from_time
 * @property string|null $to_time
 * @property array|null $title
 * @property int $is_order_detail
 * @property string|null $text_color
 * @property string|null $bg_color
 * @property-read \App\Models\DirectionExchange|null $direction_exchange
 * @property-read mixed $translations
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionNotification newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionNotification newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionNotification query()
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionNotification whereBgColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionNotification whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionNotification whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionNotification whereFromTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionNotification whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionNotification whereIdDirectionExchange($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionNotification whereIsEnabledSchedule($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionNotification whereIsOrderDetail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionNotification whereJsonContainsLocale(string $column, string $locale, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionNotification whereJsonContainsLocales(string $column, array $locales, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionNotification whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionNotification whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionNotification whereSorting($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionNotification whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionNotification whereTextColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionNotification whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionNotification whereToTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DirectionNotification whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class DirectionNotification extends Model
{
    use HasTranslations;

    protected $table = 'direction_notification';

    protected $fillable = [
        'id_direction_exchange',
        'description',
        'sorting',
        'status',
        'is_enabled_schedule',
        'from_time',
        'to_time',
        'title',
        'is_order_detail',
        'text_color',
        'bg_color',
    ];

    public $translatable = [
        'description',
        'title',
    ];

    public function direction_exchange()
    {
        return $this->hasOne(DirectionExchange::class, 'id', 'id_direction_exchange');
    }
}
