<?php
declare(strict_types=1);

namespace iEXPackages\Proxy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ProxyHealthLog
 *
 * Запись телеметрии по прокси:
 * - контекст (bestchange/parser/gateway/manual_test/…)
 * - success/http_status/latency_ms/error
 *
 * Таблица: proxy_health_logs
 */
final class ProxyHealthLog extends Model
{
    protected $table = 'proxy_health_logs';

    public $timestamps = false; // есть только created_at

    protected $fillable = [
        'proxy_id',
        'context',
        'success',
        'http_status',
        'latency_ms',
        'error',
        'created_at',
    ];

    protected $casts = [
        'proxy_id' => 'int',
        'success' => 'bool',
        'http_status' => 'int',
        'latency_ms' => 'int',
        'created_at' => 'datetime',
    ];

    public function proxy(): BelongsTo
    {
        return $this->belongsTo(Proxy::class, 'proxy_id', 'id');
    }
}
