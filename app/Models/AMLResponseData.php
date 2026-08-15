<?php

namespace App\Models;

use App\Models\Filters\AmlResponseDataFilter;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;

class AMLResponseData extends Model
{
    use Filterable;

    protected $table = 'aml_response_data';

    protected $fillable = [
        'id_aml_service',
        'alias',
        'id_task',
        'method',
        'ext_params'
    ];

    /**
     * Атрибуты, которые должны быть приведены к нативным типам.
     *
     * @return string[]
     */
    protected function casts()
    {
        return [
            'ext_params' => 'array',
        ];
    }

    /**
     * Фильтры
     */
    public function modelFilter()
    {
        return $this->provideFilter(AmlResponseDataFilter::class);
    }


    public function user()
    {
        return $this->hasOne(User::class, 'id', 'id_user');
    }
}
