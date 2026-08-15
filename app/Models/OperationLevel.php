<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationLevel extends Model
{
    protected $table = 'operation_levels';

    protected $fillable = [
        'id_level_group',
        'id_operator',
        'status',
    ];

    public function manager()
    {
        return $this->hasOne(User::class, 'id', 'id_operator');
    }

    public function level_group()
    {
        return $this->hasOne(OperatorLevelGroup::class, 'id', 'id_level_group');
    }
}
