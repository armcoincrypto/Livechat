<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string|null $text
 * @property string|null $text_alt
 * @property string|null $color
 * @property int $timeout
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|LiveNotification newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|LiveNotification newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|LiveNotification query()
 * @method static \Illuminate\Database\Eloquent\Builder|LiveNotification whereColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LiveNotification whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LiveNotification whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LiveNotification whereText($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LiveNotification whereTextAlt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LiveNotification whereTimeout($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LiveNotification whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LiveNotification extends Model
{
    protected $table = 'live_notification';

    protected $fillable = [
        'text',
        'text_alt',
        'color',
        'timeout',
    ];
}
