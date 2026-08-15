<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TasksCheckImage extends Model
{
    protected $table = 'tasks_check_images';

    protected $fillable = [
        'id_order',
        'image',
        'ip_address',
        'user_agent',
        'mimetype',
        'size'
    ];
}
