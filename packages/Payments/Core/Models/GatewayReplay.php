<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Models;

use Illuminate\Database\Eloquent\Model;

final class GatewayReplay extends Model
{
    protected $table = 'gateway_replays';

    protected $fillable = [
        'replay_key',
        'gateway',
        'operation',
        'direction',
        'http_method',
        'url',
        'request_headers',
        'request_body',
        'response_json',
        'http_status',
    ];

    protected $casts = [
        'request_headers' => 'array',
        'request_body'    => 'array',
        'response_json'   => 'array',
        'http_status'     => 'int',
    ];
}
