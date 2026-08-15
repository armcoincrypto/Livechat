<?php

declare(strict_types=1);

namespace App\Models;

use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PaymentGatewayLog extends Model
{
    use Filterable;

    protected $table = 'payment_gateway_logs';

    protected $fillable = [
        'task_id',
        'merchant_id',
        'payment_id',

        'gateway_alias',
        'direction',
        'operation',

        'transaction_id',
        'external_id',

        'http_method',
        'url',
        'response_status',
        'status',
        'duration_ms',

        'attempt',
        'idempotency_key',

        'request_headers',
        'request_body',
        'response_body',

        'error_class',
        'error_message',

        'meta',

        'is_sandbox',
        'replay_key',
    ];

    protected $casts = [
        'task_id'         => 'integer',
        'merchant_id'     => 'integer',
        'payment_id'      => 'integer',
        'response_status' => 'integer',
        'duration_ms'     => 'integer',
        'attempt'         => 'integer',
        'is_sandbox'      => 'boolean',

        'request_headers' => 'array',
        'request_body'    => 'array',
        'response_body'   => 'array',
        'meta'            => 'array',
    ];

    /**
     * Явно указываем Filter-класс, чтобы EloquentFilter всегда находил нужный фильтр.
     */
    public function modelFilter(): string
    {
        return \App\Models\Filters\PaymentGatewayLogFilter::class;
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id', 'id');
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(GatewayMerchant::class, 'merchant_id', 'id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(GatewayPayment::class, 'payment_id', 'id');
    }
}
