<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfitProfile extends Model
{
    protected $fillable = [
        'name',
        'profit',
        'profit_s',
        'is_active',
        'scope',
    ];
}
