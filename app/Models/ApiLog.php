<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiLog extends Model
{
    protected $table = 'api_logs';

    protected $fillable = [
        'api_token',
        'token_id',
        'api_action',
        'ip_address',
        'headers',
        'post_data',
        'status_code',
        'response_data',
    ];

    protected $casts = [
        'headers'       => 'array',
        'post_data'     => 'array',
        'status_code'   => 'integer',
        'response_data' => 'array'
    ];
}
