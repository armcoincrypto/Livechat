<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Banned extends Model
{
    protected $table = 'banned';

    protected $fillable = [
        'type',
        'filter_key',
        'filter_name',
        'description',
        'expired_at',
        'ip_from',
        'ip_to',
    ];

    protected $casts = [
        'expired_at' => 'datetime',
        'ip_from' => 'integer',
        'ip_to' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('expired_at', '>=', now());
    }
}
