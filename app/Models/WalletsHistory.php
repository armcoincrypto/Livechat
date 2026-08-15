<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 *
 *
 * @property int $id
 * @property int $id_task ID заявки
 * @property string|null $txid ID транзакции
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @method static \Illuminate\Database\Eloquent\Builder|WalletsHistory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|WalletsHistory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|WalletsHistory onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|WalletsHistory query()
 * @method static \Illuminate\Database\Eloquent\Builder|WalletsHistory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WalletsHistory whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WalletsHistory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WalletsHistory whereIdTask($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WalletsHistory whereTxid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WalletsHistory whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WalletsHistory withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|WalletsHistory withoutTrashed()
 * @mixin \Eloquent
 *
 * @deprecated
 */
class WalletsHistory extends Model
{
    use SoftDeletes;

    protected $table = 'wallets_history';

    protected $fillable = [
        'id_task',
        'txid',
    ];
}
