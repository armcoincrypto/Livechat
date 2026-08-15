<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Translatable\HasTranslations;

class Contact extends Model
{
    use HasTranslations;

    protected $table = 'contacts';

    protected $fillable = [
        'name',
        'value',
        'url',
        'block_size',
        'sorting',
        'status',
        'is_home',
        'icon',
        'text_color',
        'id_group',
    ];

    protected $casts = [
        'status' => 'integer',
        'is_home' => 'integer',
        'sorting' => 'integer',
        'block_size' => 'integer',
        'id_group' => 'integer',
    ];

    protected $translatable = ['name', 'value', 'url'];

    public function contact_group(): HasOne
    {
        return $this->hasOne(ContactGroup::class, 'id', 'id_group');
    }
}
