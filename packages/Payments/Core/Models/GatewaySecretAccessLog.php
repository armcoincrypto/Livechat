<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Models;

use Illuminate\Database\Eloquent\Model;

final class GatewaySecretAccessLog extends Model
{
    protected $table = 'gateway_secret_access_logs';

    protected $fillable = [
        'user_id',
        'scope',
        'action',
        'ip_address',
        'expires_at',
    ];

    protected $casts = [
        'user_id' => 'int',
    ];
}
