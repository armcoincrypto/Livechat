<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Translatable\HasTranslations;

class NewsCategory extends Model
{
    use HasTranslations;

    protected $fillable = ['name', 'slug', 'color'];

    public array $translatable = ['name'];


    protected static function booted(): void
    {
        static::creating(function ($category) {
            $category->slug ??= Str::slug($category->name);
        });
    }

    public function news(): HasMany
    {
        return $this->hasMany(News::class, 'category_id');
    }
}
