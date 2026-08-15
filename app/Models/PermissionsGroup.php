<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;

/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Permission> $permissions
 * @property-read int|null $permissions_count
 * @method static \Illuminate\Database\Eloquent\Builder|PermissionsGroup newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PermissionsGroup newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PermissionsGroup query()
 * @method static \Illuminate\Database\Eloquent\Builder|PermissionsGroup whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PermissionsGroup whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PermissionsGroup whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PermissionsGroup whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class PermissionsGroup extends Model
{
    protected $table = 'permissions_group';

    protected $fillable = [
        'id', 'name',
    ];

    public function permissions()
    {
        return $this->hasMany(Permission::class, 'id_permissions_group', 'id');
    }
}
