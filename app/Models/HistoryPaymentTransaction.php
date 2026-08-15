<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 
 *
 * @property int $id
 * @property int|null $id_task
 * @property string $payment
 * @property string $transfer
 * @property string|null $details
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryPaymentTransaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryPaymentTransaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryPaymentTransaction onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryPaymentTransaction query()
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryPaymentTransaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryPaymentTransaction whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryPaymentTransaction whereDetails($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryPaymentTransaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryPaymentTransaction whereIdTask($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryPaymentTransaction wherePayment($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryPaymentTransaction whereTransfer($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryPaymentTransaction whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryPaymentTransaction withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|HistoryPaymentTransaction withoutTrashed()
 * @mixin \Eloquent
 */
class HistoryPaymentTransaction extends Model
{
    use SoftDeletes;

    protected $table = 'history_payment_transactions';

    protected $fillable = [
        'id', 'id_task', 'payment', 'transfer', 'details',
    ];
}
