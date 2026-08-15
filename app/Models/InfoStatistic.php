<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class InfoStatistic extends Model
{
    use HasTranslations;

    protected $table = 'info_statistics';

    protected $fillable = [
        'name',
        'value',
        'link',
        'status',
        'sorting',
        'image'
    ];


    protected $translatable = ['name',  'value'];

}
