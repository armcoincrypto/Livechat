<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string|null $address
 * @property string|null $payment
 * @property string|null $email
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|PayoutAddress newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PayoutAddress newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PayoutAddress query()
 * @method static \Illuminate\Database\Eloquent\Builder|PayoutAddress whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PayoutAddress whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PayoutAddress whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PayoutAddress whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PayoutAddress wherePayment($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PayoutAddress whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class PayoutAddress extends Model
{
    protected $table = 'payout_address';

    protected $fillable = [
        'email', 'address', 'payment',
    ];
}
