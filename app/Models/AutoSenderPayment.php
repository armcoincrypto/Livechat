<?php
/**
 * Created by PhpStorm.
 * User: steei
 * Date: 16.09.2019
 * Time: 22:18
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $id_order
 * @property string|null $comment
 * @property string|null $amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|AutoSenderPayment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AutoSenderPayment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AutoSenderPayment query()
 * @method static \Illuminate\Database\Eloquent\Builder|AutoSenderPayment whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AutoSenderPayment whereComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AutoSenderPayment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AutoSenderPayment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AutoSenderPayment whereIdOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AutoSenderPayment whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class AutoSenderPayment extends Model
{
    protected $table = 'autosender_payment';

    protected $fillable = [
        'id_order',
        'comment',
        'amount',
    ];
}
