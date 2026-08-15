<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayTransactionHash extends Model
{
    protected $table = 'pay_transaction_hash';

    protected $fillable = [
        'id_task',
        'id_pay',
        'is_waiting_hash',
        'api_transfer_id',
        'transaction_hash',
        'provider',
        'id_currency',
    ];

    public function tasks()
    {
        return $this->hasOne(Task::class, 'id', 'id_task');
    }
}
