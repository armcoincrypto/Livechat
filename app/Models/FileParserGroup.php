<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int $id
 * @property string|null $name
 * @property int $status
 * @property string $link
 * @property int $sorting
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DirectionExchange> $direction_exchange
 * @property-read int|null $direction_exchange_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\FileParserRates> $file_parser_rates
 * @property-read int|null $file_parser_rates_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\FileParserRates> $rates
 * @property-read int|null $rates_count
 * @method static \Illuminate\Database\Eloquent\Builder|FileParserGroup newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|FileParserGroup newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|FileParserGroup query()
 * @method static \Illuminate\Database\Eloquent\Builder|FileParserGroup whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FileParserGroup whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FileParserGroup whereLink($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FileParserGroup whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FileParserGroup whereSorting($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FileParserGroup whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FileParserGroup whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class FileParserGroup extends Model
{
    protected $table = 'file_parser_groups';

    protected $fillable = [
        'name',
        'status',
        'link',
        'sorting',
    ];

    public function rates()
    {
        return $this->hasMany(FileParserRates::class, 'id_group', 'id');
    }

    public function rates_enabled()
    {
        return $this->hasMany(FileParserRates::class, 'id_group', 'id')->where('status', 1);
    }

    public function file_parser_rates()
    {
        return $this->hasMany(FileParserRates::class, 'id_group', 'id')->where('status', '=', 1);
    }

    public function direction_exchange()
    {
        return $this->hasMany(DirectionExchange::class, 'id_file_parser_rate', 'id');
    }
}
