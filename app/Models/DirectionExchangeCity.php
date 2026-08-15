<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class DirectionExchangeCity extends Model
{
    use HasTranslations;

    protected $table = 'direction_exchange_cities';

    protected $fillable = [
        'id_direction_exchange',
        'city_id',
        'add_comm',
        'param',
        'min_price',
        'max_price',
        'sorting',
        'instruction',
        'information',
        'profit_partner',
        'profit_partner_s',
        'profit_s',
        'profit',
        'bid_value',
        'status'
    ];


    public $translatable = [
        'instruction',
        'information',
        'exchange_text'
    ];

    protected $casts = [
        'status' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function city()
    {
        return $this->hasOne(CitiesModel::class, 'id', 'city_id');
    }

    public function profilePivot()
    {
        return $this->hasOne(DirectionExchangeCityProfilePivot::class, 'id_direction_exchange_city');
    }

    /**
     * Профиль города (many-to-many через pivot, но фактически 0..1 профиль на город).
     * Нужен для удобного sync() при сохранении.
     */
    public function cityProfiles()
    {
        return $this->belongsToMany(
            DirectionCityProfile::class,
            'directions_city_profile_pivot',
            'id_direction_exchange_city',
            'direction_city_profile_id'
        );
    }

    public function profile()
    {
        return $this->hasOneThrough(
            DirectionCityProfile::class,
            DirectionExchangeCityProfilePivot::class,
            'id_direction_exchange_city',
            'id',
            'id',
            'direction_city_profile_id'
        );
    }
}
