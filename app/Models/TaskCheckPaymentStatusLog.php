<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskCheckPaymentStatusLog extends Model
{
    protected $fillable = [
        'task_id',
        'old_status',
        'new_status',
        'description'
    ];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}
