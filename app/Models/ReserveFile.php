<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $id_group
 * @property string|null $name
 * @property float $amount
 * @property int $status
 * @property int $number_format
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\ReserveFileGroup|null $file_group
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Reserve> $reserves
 * @property-read int|null $reserves_count
 * @method static \Illuminate\Database\Eloquent\Builder|ReserveFile newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ReserveFile newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ReserveFile query()
 * @method static \Illuminate\Database\Eloquent\Builder|ReserveFile whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ReserveFile whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ReserveFile whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ReserveFile whereIdGroup($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ReserveFile whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ReserveFile whereNumberFormat($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ReserveFile whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ReserveFile whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class ReserveFile extends Model
{
    protected $table = 'reserves_files';

    protected $fillable = [
        'name',
        'id_group',
        'amount',
        'status',
        'number_format',
    ];

    public function reserves()
    {
        return $this->hasMany(Reserve::class, 'id_file_reserve', 'id');
    }

    public function file_group()
    {
        return $this->hasOne(ReserveFileGroup::class, 'id', 'id_group');
    }
}
