<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $id_payment
 * @property string|null $link
 * @property int $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $text
 * @property-read \App\Models\Payment|null $payment
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentExplorer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentExplorer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentExplorer query()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentExplorer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentExplorer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentExplorer whereIdPayment($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentExplorer whereLink($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentExplorer whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentExplorer whereText($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentExplorer whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class PaymentExplorer extends Model
{
    protected $table = 'payment_explorer';

    protected $fillable = [
        'id_payment',
        'link',
        'text',
        'status',
    ];

    public function payment()
    {
        return $this->hasOne(Payment::class, 'id', 'id_payment');
    }
}
