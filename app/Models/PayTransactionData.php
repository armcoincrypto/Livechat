<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PayTransactionData extends Model
{
    protected $table = 'pays_transaction_data';


    protected $fillable = [
        'id_task',
        'id_currency',
        'id_pay',
        'id_from_pay',
        'service_name',
        'ext_data',
        'id_direction_exchange'
    ];

    protected function casts()
    {
        return [
            'ext_data' => 'array',
        ];
    }


    public function tasks(): HasOne
    {
        return $this->hasOne(Task::class, 'id', 'id_task');
    }

    public function pay(): HasOne
    {
        return $this->hasOne(GatewayPayment::class, 'id', 'id_pay');
    }


    /**
     * JSON-path update via query builder so JSON_SET/jsonb_set is used.
     * Returns affected rows.
     */
    public function jsonUpdate(array $attributes): int
    {
        return static::whereKey($this->getKey())->update($attributes);
    }
}
