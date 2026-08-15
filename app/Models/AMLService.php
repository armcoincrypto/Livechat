<?php

namespace App\Models;

use App\Models\Filters\AMLServiceFilter;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;

class AMLService extends Model
{
    use Filterable;

    protected $table = 'aml_services';

    protected $fillable = [
        'name',
        'status',
        'id_manager',
        'alias',
        'aml_name',
        'filename',
        'ext_options'
    ];

    protected $casts = [
        'ext_options' => 'array'
    ];

    /**
     * Фильтры городов
     *
     * @return string|null
     */
    public function modelFilter(): ?string
    {
        return $this->provideFilter(AMLServiceFilter::class);
    }
}
