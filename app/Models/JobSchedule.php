<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobSchedule extends Model
{
    protected $table = 'job_schedules';

    protected $fillable = [
        'id_user','name','status','all_day','outside_policy','priority',
        'from_time','to_time','timezone','active_from','active_to',
        'work_days','include_dates','exclude_dates','date_ranges',
    ];

    protected $casts = [
        'status'        => 'boolean',
        'all_day'       => 'boolean',
        'priority'      => 'integer',
        'active_from'   => 'date',
        'active_to'     => 'date',
        'include_dates' => 'array',
        'exclude_dates' => 'array',
        'date_ranges'   => 'array',
    ];
}
