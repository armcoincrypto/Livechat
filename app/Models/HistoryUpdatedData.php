<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistoryUpdatedData extends Model
{
    protected $table = 'histories_updated_data';

    protected $fillable = [
        'type_update',
        'count_num',
        'total_num',
        'time',
        'old_time'
    ];
}
