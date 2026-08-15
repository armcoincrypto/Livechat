<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantTransactionWebhook extends Model
{
    protected $table = 'merchant_transaction_webhooks';


    protected $fillable = [
        'id_task',
        'merchant_service_id',
        'id_currency',
        'id_merchant',
        'provider',
        'json_callbacks',
        'amount',
        'ext_params'
    ];

    public function tasks()
    {
        return $this->hasOne(Task::class, 'id', 'id_task');
    }
}
