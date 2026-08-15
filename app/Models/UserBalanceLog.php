<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $id_user
 * @property int $type
 * @property int $id_manager
 * @property string|null $text
 * @property string|null $from_balance
 * @property string|null $to_balance
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $route_type
 * @property-read \App\Models\User|null $manager
 * @method static \Illuminate\Database\Eloquent\Builder|UserBalanceLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserBalanceLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserBalanceLog query()
 * @method static \Illuminate\Database\Eloquent\Builder|UserBalanceLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserBalanceLog whereFromBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserBalanceLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserBalanceLog whereIdManager($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserBalanceLog whereIdUser($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserBalanceLog whereRouteType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserBalanceLog whereText($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserBalanceLog whereToBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserBalanceLog whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserBalanceLog whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class UserBalanceLog extends Model
{
    protected $table = 'user_balance_log';

    protected $fillable = [
        'id_user',
        'id_manager',
        'type',
        'route_type',
        'text',
        'from_balance',
        'to_balance',
    ];

    public function manager()
    {
        return $this->hasOne(User::class, 'id', 'id_manager');
    }
}
