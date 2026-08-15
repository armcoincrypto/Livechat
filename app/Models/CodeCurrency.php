<?php

namespace App\Models;

use App\Models\Filters\CodeCurrencyFilter;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class CodeCurrency extends Model
{
    use Filterable;

    public $table = 'code_currency';

    protected $fillable = [
        'name',
        'sign',
        'balance',
        'id_parser_exchange', //delete
        'add_to_course', //delete
        'is_trashed',
        'internal_rate',
        'id_parser_formula', //delete
        'add_to_course_formula', // delete
    ];

    /**
     * Interact with the user's first name.
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => $value,
            set: fn (string $value) => Str::upper($value),
        );
    }

    /**
     * Фильтры
     */
    public function modelFilter()
    {
        return $this->provideFilter(CodeCurrencyFilter::class);
    }

    public function scopeActive(Builder $query)
    {
        return $query->where('is_trashed', '=', 0);
    }

    public function reserves()
    {
        $currencies = Currency::where('id_code_currency', $this->id)->pluck('id');

        return Reserve::whereIn('id_currency', $currencies)->sum('summa');
    }

    public function internal_account(): HasOne
    {
        return $this->hasOne(InternalAccount::class, 'id_code_currency', 'id')->where('id_user', '=', auth()->id());
    }

    public function currency(): HasMany
    {
        return $this->hasMany(Currency::class, 'id_code_currency', 'id');
    }
}
