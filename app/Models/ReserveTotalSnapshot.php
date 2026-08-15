<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReserveTotalSnapshot extends Model
{
    protected $table = 'reserve_total_snapshots';

    protected $fillable = [
        'snapshot_at',
        'total_usd',
    ];

    protected $casts = [
        'snapshot_at' => 'datetime',
    ];
}
