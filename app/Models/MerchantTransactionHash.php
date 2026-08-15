<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantTransactionHash extends Model
{
    protected $table = 'merchant_transaction_hash';

    protected $fillable = [
        'id_task',
        'transaction_hash',
        'provider',
        'id_currency',
    ];

    public function tasks()
    {
        return $this->hasOne(Task::class, 'id', 'id_task');
    }
}
