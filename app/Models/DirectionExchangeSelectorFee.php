<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class DirectionExchangeSelectorFee extends Model
{
    use HasTranslations;

    protected $table = 'direction_exchange_selector_fee';

    protected $fillable = [
        'id_direction_exchange',
        'fee',
        'name',
        'description'
    ];


    public $translatable = [
        'name',
        'description'
    ];
}
