<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int $id
 * @property int $id_user
 * @property string|null $wallet
 * @property int $id_currency
 * @property int $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|UserWalletStories newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserWalletStories newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserWalletStories query()
 * @method static \Illuminate\Database\Eloquent\Builder|UserWalletStories whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserWalletStories whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserWalletStories whereIdCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserWalletStories whereIdUser($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserWalletStories whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserWalletStories whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserWalletStories whereWallet($value)
 * @mixin \Eloquent
 */
class UserWalletStories extends Model
{
    protected $table = 'user_wallet_stories';

    protected $fillable = [
        'id_user',
        'wallet',
        'id_currency',
        'status',
        'usage_count',
    ];
}
