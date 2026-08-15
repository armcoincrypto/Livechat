<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class PageGroup extends Model
{
    use HasTranslations;

    protected $table = 'page_groups';

    protected $fillable = [
        'slug',
        'title',
        'description',
        'sort_order',
        'is_active',
    ];

    /**
     * Мультиязычные поля
     */
    public array $translatable = [
        'title',
        'description',
    ];

    /**
     * Страницы в группе
     */
    public function pages()
    {
        return $this->hasMany(Page::class, 'group_id', 'id')
            ->where('is_active', true)
            ->orderBy('sort_order');
    }
}
