<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProxyModel extends Model
{
    protected $table = 'proxies';

    protected $fillable = [
        'host',
        'port',
        'login',
        'password',
        'type',
        'status',
        'last_checked_at',
        'fail_count',
    ];

    protected $casts = [
        'last_checked_at' => 'datetime',
    ];

    // Если есть связь с реквизитами — оставь, если нет — удали!
    // public function requisites()
    // {
    //     return $this->hasMany(Requisites::class, 'id_proxy', 'id');
    // }
}
