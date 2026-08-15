<?php

namespace App\Models;

use App\Models\Casts\JsonCasts;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string $alias
 * @property string $name
 * @property string $class_name
 * @property int $is_merchant Доступен прием?
 * @property int $is_pay Доступны выплаты?
 * @property int $is_rpc Использует RPC соединение
 * @property int $is_check_pay Проверка поступления средств
 * @property string $version
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property array|null $security_options
 * @property int $status
 * @method static \Illuminate\Database\Eloquent\Builder|Gateway newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Gateway newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Gateway query()
 * @method static \Illuminate\Database\Eloquent\Builder|Gateway whereAlias($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Gateway whereClassName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Gateway whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Gateway whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Gateway whereIsCheckPay($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Gateway whereIsMerchant($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Gateway whereIsPay($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Gateway whereIsRpc($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Gateway whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Gateway whereSecurityOptions($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Gateway whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Gateway whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Gateway whereVersion($value)
 * @mixin \Eloquent
 */
class Gateway extends Model
{
    protected $fillable = [
        'name',
        'alias',
        'class_name',
        'status',
        'is_merchant',
        'is_pay',
        'is_check_pay',
        'is_rpc',
        'version',
        'security_options',
    ];

    protected $casts = [
        'security_options' => JsonCasts::class,
    ];
}
