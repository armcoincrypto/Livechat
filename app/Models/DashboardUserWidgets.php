<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DashboardUserWidgets extends Model
{
    protected $table = 'dashboard_user_widgets';

    protected $fillable = [
        'id_user',
        'sorting',
        'row',
        'col',
        'alias',
    ];
}
