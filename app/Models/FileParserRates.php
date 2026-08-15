<?php

namespace App\Models;


use App\Models\Filters\FileParserRatesFilter;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;


class FileParserRates extends Model
{
    use Filterable;

    protected $table = 'file_parser_rates';

    protected $fillable = [
        'name',
        'exchange_in',
        'exchange_out',
        'id_group',
        'value',
        'summa',
        'status',
        'number_format',
        'type',
        'code',
    ];

    /**
     * Фильтры
     *
     * @return string|null
     */
    public function modelFilter(): ?string
    {
        return $this->provideFilter(FileParserRatesFilter::class);
    }

    public function duplicate_count()
    {
        return $this->hasMany(FileParserRates::class, 'name', 'name')->count();
    }

    public function direction_exchange()
    {
        return $this->hasMany(DirectionExchange::class, 'id_file_parser_rate', 'id');
    }

    public function file_parser_group()
    {
        return $this->hasOne(FileParserGroup::class, 'id', 'id_group');
    }
}
