<?php

namespace App\Models;

use App\Models\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


class Menu extends Model
{
    use HasTranslations;

    protected $table = 'menu';

    protected $fillable = [
        'name',
        'name_alt',
        'sorting',
        'slug',
        'status',
        'text_color',
        'parent_id'
    ];

    public array $translatable = ['name'];

    /**
     * Родительская категория
     *
     * @return HasMany
     */
    public function children(): HasMany
    {
        return $this->hasMany(Menu::class, 'parent_id', 'id');
    }

    /**
     * Подкатегория
     *
     * @return BelongsTo
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }
}
