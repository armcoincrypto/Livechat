<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MerchantTransactionData extends Model
{
    protected $table = 'merchants_transaction_data';


    protected $fillable = [
        'id_task',
        'id_currency',
        'id_merchant',
        'id_from_merchant',
        'service_name',
        'ext_data',
        'is_checkout_url',
        'id_direction_exchange',
        'account_validator_type',
        'account_validator_passed',
    ];

    protected function casts()
    {
        return [
            'ext_data' => 'array',
            'account_validator_passed' => 'boolean',
        ];
    }


    public function tasks(): HasOne
    {
        return $this->hasOne(Task::class, 'id', 'id_task');
    }

    public function merchant(): HasOne
    {
        return $this->hasOne(GatewayMerchant::class, 'id', 'id_merchant');
    }
}
