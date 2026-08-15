<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderExport extends Model
{
    protected $fillable = [
        'user_id',
        'format',
        'file_name',
        'status',
        'rows_count',
        'filters',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'filters'     => 'array',
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
