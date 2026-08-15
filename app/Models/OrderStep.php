<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class OrderStep extends Model
{
    use HasTranslations;

    protected $table = 'order_steps';

    public $translatable = ['name'];

    protected $fillable = [
        'name',
        'id_manager',
        'status',
        'sorting'
    ];
}
