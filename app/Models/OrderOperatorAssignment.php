<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderOperatorAssignment extends Model
{
    protected $table = 'order_operator_assignments';

    protected $fillable = [
        'task_id',
        'operator_user_id',
        'telegram_user_id',
        'source',
        'claimed_at',
        'released_at',
    ];

    protected $casts = [
        'task_id' => 'integer',
        'operator_user_id' => 'integer',
        'telegram_user_id' => 'integer',
        'claimed_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id', 'id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_user_id', 'id');
    }

    public function isActive(): bool
    {
        return $this->released_at === null;
    }
}
