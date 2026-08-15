<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * 
 *
 * @property int $id
 * @property int $id_user
 * @property int $id_requisite
 * @property int $id_currency
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $account
 * @property string|null $old_account
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder|RequisiteLogs newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|RequisiteLogs newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|RequisiteLogs query()
 * @method static \Illuminate\Database\Eloquent\Builder|RequisiteLogs whereAccount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequisiteLogs whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequisiteLogs whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequisiteLogs whereIdCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequisiteLogs whereIdRequisite($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequisiteLogs whereIdUser($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequisiteLogs whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequisiteLogs whereOldAccount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequisiteLogs whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequisiteLogs whereUserAgent($value)
 * @mixin \Eloquent
 */
class RequisiteLogs extends Model
{
    protected $table = 'requisites_logs';

    protected $fillable = [
        'id_user',
        'ip_address',
        'id_requisite',
        'user_agent',
        'account',
        'old_account'
    ];

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'id_user');
    }
}
