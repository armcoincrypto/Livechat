<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $id_step
 * @property int $id_manager
 * @property int $id_task
 * @property int $id_order_status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $manager
 * @property-read \App\Models\TaskStatus|null $order_status
 * @property-read \App\Models\OrderStep|null $order_step
 * @method static \Illuminate\Database\Eloquent\Builder|ApplicationStepLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ApplicationStepLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ApplicationStepLog query()
 * @method static \Illuminate\Database\Eloquent\Builder|ApplicationStepLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ApplicationStepLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ApplicationStepLog whereIdManager($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ApplicationStepLog whereIdOrderStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ApplicationStepLog whereIdStep($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ApplicationStepLog whereIdTask($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ApplicationStepLog whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class ApplicationStepLog extends Model
{
    protected $table = 'applications_steps_logs';

    protected $fillable = [
        'id_step',
        'id_manager',
        'id_task',
        'id_order_status',
    ];

    public function manager()
    {
        return $this->hasOne(User::class, 'id', 'id_manager');
    }

    public function order_step()
    {
        return $this->hasOne(OrderStep::class, 'id', 'id_step');
    }

    public function order_status()
    {
        return $this->hasOne(TaskStatus::class, 'id', 'id_order_status');
    }
}
