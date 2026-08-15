<?php
declare(strict_types=1);

namespace iEXPackages\Proxy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Proxy
 *
 * Единая модель прокси для всех модулей.
 *
 * Важно:
 * - Использует существующую таблицу `proxies`.
 * - Тебе не нужно менять БД, если таблица уже есть.
 *
 * Поля (как у тебя):
 * - host, port, login, password, type, status, last_checked_at, fail_count
 */
final class Proxy extends Model
{
    protected $table = 'proxies';

    protected $fillable = [
        'host',
        'port',
        'login',
        'password',
        'type',
        'status',
        'last_checked_at',
        'fail_count',
        'auto_disabled_until'
    ];

    protected $casts = [
        'port' => 'int',
        'status' => 'bool',
        'fail_count' => 'int',
        'last_checked_at' => 'datetime',
        'auto_disabled_until' => 'datetime',
        'password' => 'encrypted',
    ];

    public function healthLogs(): HasMany
    {
        return $this->hasMany(ProxyHealthLog::class, 'proxy_id', 'id');
    }
}
