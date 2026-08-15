<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $id_task
 * @property string|null $exchange_usd
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 *
 * @property-read \App\Models\Task $task
 */
class OrderExchangeTotal extends Model
{
    use SoftDeletes;

    protected $table = 'order_exchange_totals';

    protected $fillable = [
        'id_task',
        'exchange_usd',
    ];

    protected $casts = [
        'exchange_usd' => 'decimal:18',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class, 'id_task', 'id');
    }

    public function scopeBetweenDates($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }
}
