<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralInfoLog extends Model
{
    protected $table = 'referrals_info_logs';

    protected $fillable = [
        'id_referral',
        'id_user',
        'type',
        'text',
    ];

    public function referral()
    {
        return $this->hasOne(User::class, 'id', 'id_referral');
    }

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'id_user');
    }
}
