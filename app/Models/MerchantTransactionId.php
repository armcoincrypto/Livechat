<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantTransactionId extends Model
{
    protected $table = 'merchant_transaction_ids';

    protected $fillable = [
        'id_task',
        'transaction_id',
        'provider',
    ];

    public function tasks()
    {
        return $this->hasOne(Task::class, 'id', 'id_task');
    }
}
