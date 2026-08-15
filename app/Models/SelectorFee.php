<?php

namespace App\Models;

use App\Models\Traits\HasTranslations;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SelectorFee extends Model
{
    use HasFactory, HasTranslations;

    protected $table = 'selector_fees';

    protected $fillable = [
        'name',
        'fee',
        'sorting',
        'status',
        'description',
        'fee_type'
    ];

    public $translatable = ['name', 'description'];

    /**
     * Направления обмена, которые используют данную комиссию.
     *
     * @return BelongsToMany
     */
    public function excludedDirections(): BelongsToMany
    {
        return $this->belongsToMany(
            DirectionExchange::class,
            'selector_fee_direction_exchange',
            'selector_fee_id',
            'direction_exchange_id'
        );
    }
}
