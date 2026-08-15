<?php

namespace App\Models;

use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

class WithdrawalRequest extends Model
{
    use Filterable, Notifiable, SoftDeletes;

    protected $table = 'withdrawal_request';

    protected $fillable = [
        'id_user',
        'id_manager',
        'id_currency',
        'referral',
        'reward',
        'score',
        'status',
        'balance_referral',
        'base_referral',
        'ip',
        'verified_at',
        'tx_id',
        'big_id',
        'is_black_list',
        'black_list_text',
        'view_balance_referral',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function modelFilter()
    {
        return $this->provideFilter(\App\Models\Filters\WithdrawalRequestFilter::class);
    }

    public function currency()
    {
        return $this->hasOne(Currency::class, 'id', 'id_currency');
    }

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'id_user');
    }

    public function manager()
    {
        return $this->hasOne(User::class, 'id', 'id_manager');
    }

    public function withdrawal_request_log()
    {
        return $this->hasOne(WithdrawalRequestLog::class, 'id_withdrawal_request', 'id');
    }
}
