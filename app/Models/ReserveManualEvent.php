<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReserveManualEvent extends Model
{
    protected $table = 'reserves_manual_events';

    protected $fillable = [
        'id_user',
        'id_reserve',
        'type_reserve',
        'reserve_from',
        'reserve_to',
        'comment',
    ];


    /**
     * Данные пользователя
     *
     * @return HasOne
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'id_user');
    }
}
