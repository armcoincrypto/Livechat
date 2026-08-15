<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaskInfo extends Model
{
    use SoftDeletes;

    protected $table = 'tasks_info';

    protected $casts = [
        'recalculated_at' => 'datetime',
        'is_recounted_by_merchant' => 'boolean'
    ];

    protected $fillable = [
        'id_task',
        'ip',
        'device',
        'newbie',
        'language',
        'is_not_partner',
        'in_min_amount',
        'in_max_amount',
        'is_freeze_scam',
        'type_freeze_scam', // удалить
        'num_transaction',
        'notify_statuses',
        'count_change_operator',
        'note_tx',
        'is_pending',
        'blockchain_confirm',
        'blockchain_hash',
        'id_transaction_merchant',
        'id_transaction_pay',
        'city_name',
        'autopayout_token',
        'autopayout_started_at',
        'is_aml_analysis',
        'is_wait_hash_pay',
        'recalculated_at',
        'country_name',
        'is_blocked_chat',
        'dot_not_remember_data',
        'is_aml_high_risk',
        'city_id',
        'direction_city_id',
        'is_recounted_by_merchant'
    ];

    public function directionCity()
    {
        return $this->hasOne(DirectionExchangeCity::class, 'id', 'direction_city_id');
    }

    public function city()
    {
        return $this->belongsTo(CitiesModel::class, 'city_id');
    }

    public function pay_transaction_hash()
    {
        return $this->hasOne(PayTransactionHash::class, 'id_task', 'id_task');
    }

    public function tasks()
    {
        return $this->hasOne(Task::class, 'id', 'id_task');
    }
}
