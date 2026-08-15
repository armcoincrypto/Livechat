<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DirectionCityProfile extends Model
{
    protected $table = 'direction_city_profiles';

    protected $fillable = [
        'name',
        'code',
        'profit',
        'profit_s',
        'add_comm',
        'status',
    ];

    protected $casts = [
        'profit' => 'decimal:8',
        'profit_s' => 'decimal:8',
        'status' => 'boolean',
    ];

    public function pivotCities()
    {
        return $this->hasMany(DirectionExchangeCityProfilePivot::class, 'direction_city_profile_id');
    }
}
