<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class FaqCategory extends Model
{
    use HasTranslations;

    protected $table = 'faq_category';

    protected $fillable = [
        'name',
        'sorting',
        'status',
    ];

    protected $casts = [
        'sorting' => 'integer',
        'status' => 'integer',
    ];

    public $translatable = ['name'];

    public function faq()
    {
        return $this->hasMany(Faq::class, 'id_group', 'id');
    }
}
