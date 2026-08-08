<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class BestChangeMarketReport extends Model
{
    protected $table = 'bestchange_market_reports';

    protected $fillable = ['day','payload'];

    protected $casts = [
        'day' => 'date',
        'payload' => 'array',
    ];
}
