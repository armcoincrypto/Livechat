<?php
declare(strict_types=1);

namespace iEXPackages\OnlinePresence\Models;

use Illuminate\Database\Eloquent\Model;

final class OnlineSession extends Model
{
    protected $table = 'online_sessions';

    protected $fillable = [
        'type',
        'identity',
        'user_id',
        'gid',
        'user_name',
        'user_email',
        'first_seen',
        'last_seen',
        'hits',
        'ip',
        'ua_hash',
    ];

    protected $casts = [
        'first_seen' => 'datetime',
        'last_seen'  => 'datetime',
    ];
}
