<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;


class CurrencyGroupNetwork extends Model
{
    use HasTranslations;

    protected $table = 'currencies_groups_networks';

    protected $fillable = [
        'title',
        'icon',
        'status',
        'display_type'
    ];

    public $translatable = ['title'];


    /**
     * Модель валют
     *
     * @return HasMany
    */
    public function currencies(): HasMany
    {
        return $this->hasMany(Currency::class, 'id_group_network', 'id');
    }
}
