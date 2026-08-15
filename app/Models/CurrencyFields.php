<?php

namespace App\Models;

use App\Models\Filters\CurrencyFieldsFilter;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\Translatable\HasTranslations;

class CurrencyFields extends Model
{
    use HasTranslations,
        Filterable;

    protected $table = 'currency_fields';

    protected $fillable = [
        'name',
        'min_char',
        'max_char',
        'obligatory_field',
        'status',
        'key_id',
        'field_type',
        'language_field',
        'when_print',
        'remove_spaces',
        'sorting',
        'sorting_out',
        'type_field',
        'list_text',
        'start_with',
        'end_with',
        'description_field',
        'example',
        'validator_type'
    ];

    public $translatable = [
        'name',
        'list_text',
        'description_field',
        'example'
    ];


    /**
     * Фильтры
     *
     * @return string|null
     */
    public function modelFilter(): ?string
    {
        return $this->provideFilter(CurrencyFieldsFilter::class);
    }

    public function currency(): HasOne
    {
        return $this->hasOne(Currency::class, 'id', 'id_currency');
    }

    public function currencies_in(): MorphToMany
    {
        return $this->morphedByMany(Currency::class, 'model', 'currency_in_has_fields', 'field_id');
    }

    public function currencies_out(): MorphToMany
    {
        return $this->morphedByMany(Currency::class, 'model', 'currency_out_has_fields', 'field_id');
    }
}
