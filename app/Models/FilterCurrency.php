<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Translatable\HasTranslations;

class FilterCurrency extends Model
{
    use HasTranslations;

    protected $table = 'filter_currency';

    protected $fillable = [
        'name',
        'icon',
        'sorting',
    ];

    public $translatable = ['name'];

    public function currencies(): BelongsToMany
    {
        return $this->belongsToMany(
            Currency::class,
            'currency_filter',
            'filter_currency_id',
            'currency_id'
        );
    }
}
