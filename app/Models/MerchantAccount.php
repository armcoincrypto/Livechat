<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $id_task
 * @property string|null $account
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $provider
 * @property-read \App\Models\Task|null $tasks
 * @method static \Illuminate\Database\Eloquent\Builder|MerchantAccount newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|MerchantAccount newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|MerchantAccount query()
 * @method static \Illuminate\Database\Eloquent\Builder|MerchantAccount whereAccount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MerchantAccount whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MerchantAccount whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MerchantAccount whereIdTask($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MerchantAccount whereProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MerchantAccount whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class MerchantAccount extends Model
{
    protected $table = 'merchant_account';

    protected $fillable = [
        'id_task',
        'account',
        'provider',
    ];

    public function tasks()
    {
        return $this->hasOne(Task::class, 'id', 'id_task');
    }
}
