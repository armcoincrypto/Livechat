<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Translatable\HasTranslations;

class CurrencyCategory extends Model
{
    use HasTranslations;

    protected $table = 'currency_categories';

    protected $fillable = [
        'code',
        'title',
        'position',
        'is_active',
    ];

    public array $translatable = [
        'title',
    ];

    protected $casts = [
        'title' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Валюты внутри категории.
     * Pivot: position, is_active
     */
    public function currencies(): BelongsToMany
    {
        return $this->belongsToMany(
            Currency::class,
            'currency_category_items',
            'currency_category_id',
            'currency_id'
        )
            ->withPivot(['position', 'is_active'])
            ->withTimestamps()
            ->orderBy('currency_category_items.position');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
