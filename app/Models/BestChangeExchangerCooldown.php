<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class BestChangeExchangerCooldown extends Model
{
    protected $table = 'bestchange_exchanger_cooldowns';

    protected $fillable = [
        'changer_id','blocked_until','reason','minutes','meta',
    ];

    protected $casts = [
        'blocked_until' => 'datetime',
        'meta' => 'array',
    ];
}
