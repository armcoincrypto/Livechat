<?php

namespace App\Models;

use App\Models\Filters\RequisiteInfoFieldFilter;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\Translatable\HasTranslations;

class RequisiteInfoField extends Model
{
    use HasTranslations;
    use Filterable;

    protected $table = 'requisites_info_fields';

    public $translatable = [
        'key_name',
        'value_name'
    ];

    protected $fillable = [
        'key_name',
        'value_name',
        'status',
        'user_id',
        'sorting'
    ];

    /**
     * Фильтры
     */
    public function modelFilter()
    {
        return $this->provideFilter(RequisiteInfoFieldFilter::class);
    }


    public function requisites(): MorphToMany
    {
        return $this->morphedByMany(
            Requisites::class,
            'model',
            'requisites_has_info_fields',
            'field_id'
        );
    }
}
