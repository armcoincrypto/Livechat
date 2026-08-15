<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DirectionExchangeCityProfilePivot extends Model
{
    protected $table = 'directions_city_profile_pivot';

    protected $fillable = [
        'id_direction_exchange_city',
        'direction_city_profile_id',
    ];

    public function city()
    {
        return $this->belongsTo(DirectionExchangeCity::class, 'id_direction_exchange_city');
    }

    public function profile()
    {
        return $this->belongsTo(DirectionCityProfile::class, 'direction_city_profile_id');
    }
}
