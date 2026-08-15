<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int                $id
 * @property \Carbon\Carbon     $date
 * @property int                $total_count
 * @property int                $completed_count
 * @property int                $rejected_count
 * @property int                $processing_count
 * @property \Carbon\Carbon     $created_at
 * @property \Carbon\Carbon     $updated_at
 */
class OrderExchangeStatDaily extends Model
{
    protected $table = 'order_exchange_stats_daily';

    protected $fillable = [
        'date',
        'total_count',
        'completed_count',
        'rejected_count',
        'processing_count',
    ];

    protected $casts = [
        'date' => 'date',
    ];
}
