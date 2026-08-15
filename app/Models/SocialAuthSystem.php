<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string|null $name
 * @property string|null $alias
 * @property string|null $client_id
 * @property string|null $client_secret
 * @property int $sorting
 * @property int $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|SocialAuthSystem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|SocialAuthSystem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|SocialAuthSystem query()
 * @method static \Illuminate\Database\Eloquent\Builder|SocialAuthSystem whereAlias($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SocialAuthSystem whereClientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SocialAuthSystem whereClientSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SocialAuthSystem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SocialAuthSystem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SocialAuthSystem whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SocialAuthSystem whereSorting($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SocialAuthSystem whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SocialAuthSystem whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class SocialAuthSystem extends Model
{
    protected $table = 'social_auth_system';

    protected $fillable = [
        'name',
        'alias',
        'client_id',
        'client_secret',
        'sorting',
        'status',
    ];
}
