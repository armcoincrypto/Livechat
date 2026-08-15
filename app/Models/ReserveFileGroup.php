<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string|null $name
 * @property int $status
 * @property string|null $link
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ReserveFile> $reserves_files
 * @property-read int|null $reserves_files_count
 * @method static \Illuminate\Database\Eloquent\Builder|ReserveFileGroup newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ReserveFileGroup newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ReserveFileGroup query()
 * @method static \Illuminate\Database\Eloquent\Builder|ReserveFileGroup whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ReserveFileGroup whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ReserveFileGroup whereLink($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ReserveFileGroup whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ReserveFileGroup whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ReserveFileGroup whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class ReserveFileGroup extends Model
{
    protected $table = 'reserves_files_groups';

    protected $fillable = [
        'name',
        'status',
        'link',
    ];

    public function reserves_files()
    {
        return $this->hasMany(ReserveFile::class, 'id_group', 'id');
    }
}
