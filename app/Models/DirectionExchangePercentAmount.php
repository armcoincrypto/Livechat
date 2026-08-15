<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DirectionExchangePercentAmount extends Model
{
    protected $table = 'direction_exchange_percent_amount';

    protected $fillable = [
        'id_direction_exchange',
        'percentage',
        'from_amount',
        'to_amount',
    ];
}
