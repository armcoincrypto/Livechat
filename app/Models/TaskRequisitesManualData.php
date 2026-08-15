<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskRequisitesManualData extends Model
{
    protected $table = 'tasks_requisites_manual_data';

    protected $fillable = [
        'id_requisites',
        'id_task',
        'account_number',
        'ext_params',
    ];

    protected function casts()
    {
        return [
            'ext_params' => 'array',
        ];
    }
}
