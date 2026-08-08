<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class BestChangeExchangerStat extends Model
{
    protected $table = 'bestchange_exchanger_stats';

    protected $fillable = [
        'changer_id',
        'seen_count',
        'selected_count',
        'rejected_count',
        'error_count',
        'quality_score_sum',
        'last_seen_at',
        'last_selected_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'last_selected_at' => 'datetime',
    ];
}
