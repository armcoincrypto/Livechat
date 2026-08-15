<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationEvent extends Model
{
    protected $fillable = [
        'is_read',
        'type_event',
        'title',
        'message',
        'id_value'
    ];

    protected $casts = [
        'message' => 'array'
    ];
}
