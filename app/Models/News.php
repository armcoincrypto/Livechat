<?php
/**
 * Created by PhpStorm.
 * User: steei
 * Date: 02.10.2017
 * Time: 16:47
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class News extends Model
{
    use HasTranslations;

    protected $table = 'news';

    public $translatable = ['name', 'text'];

    protected $fillable = [
        'name',
        'cr_text',
        'text',
        'parent_url',
        'image',
        'views',
        'slug_name',
        'is_local_image',
        'category_id'
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(NewsCategory::class, 'category_id');
    }
}
