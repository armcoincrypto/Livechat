<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Logging\Services;

use App\Models\PaymentGatewayLog;
use iEXPackages\Payments\Logging\SensitiveDataMasker;

final class GatewayLogFormatter
{
    public function __construct(
        private readonly SensitiveDataMasker $masker,
    ) {}

    public function format(PaymentGatewayLog $log): array
    {
        $requestHeaders = is_array($log->request_headers ?? null) ? $log->request_headers : [];
        $requestBody    = is_array($log->request_body ?? null) ? $log->request_body : [];
        $responseBody   = is_array($log->response_body ?? null) ? $log->response_body : [];

        $safeReqHeaders = $this->masker->mask($requestHeaders);
        $safeReqBody    = $this->masker->mask($requestBody);
        $safeRespBody   = $this->masker->mask($responseBody);

        return [
            'id'        => (int) $log->id,
            'createdAt' => $log->created_at?->toIso8601String(),
            'updatedAt' => $log->updated_at?->toIso8601String(),

            'gateway'   => (string) $log->gateway_alias,
            'direction' => (string) $log->direction,
            'operation' => (string) $log->operation,

            'links' => [
                'taskId'     => $log->task_id ? (int) $log->task_id : null,
                'merchantId' => $log->merchant_id ? (int) $log->merchant_id : null,
                'paymentId'  => $log->payment_id ? (int) $log->payment_id : null,
            ],

            'refs' => [
                'task' => isset($log->task) && $log->task ? [
                    'id'     => (int) $log->task->id,
                    'status' => $log->task->status ?? null,
                ] : null,

                'merchant' => isset($log->merchant) && $log->merchant ? [
                    'id'    => (int) $log->merchant->id,
                    'alias' => $log->merchant->alias ?? null,
                    'name'  => $log->merchant->name ?? null,
                ] : null,

                'payment' => isset($log->payment) && $log->payment ? [
                    'id'    => (int) $log->payment->id,
                    'alias' => $log->payment->alias ?? null,
                    'name'  => $log->payment->name ?? null,
                ] : null,
            ],

            'ids' => [
                'transactionId'  => $log->transaction_id ?: null,
                'externalId'     => $log->external_id ?: null,
                'idempotencyKey' => $log->idempotency_key ?: null,
                'replayKey'      => $log->replay_key ?: null,
            ],

            'flags' => [
                'isSandbox' => (bool) ($log->is_sandbox ?? false),
            ],

            'http' => [
                'method'         => $log->http_method ?: null,
                'url'            => $log->url ?: null,
                'responseStatus' => $log->response_status !== null ? (int) $log->response_status : null,
                'gatewayStatus'  => $log->status ?: null,
                'timeMs'         => $log->duration_ms !== null ? (int) $log->duration_ms : null,
                'attempt'        => $log->attempt !== null ? (int) $log->attempt : 1,
            ],

            'request' => [
                'headers' => $safeReqHeaders,
                'body'    => $safeReqBody,
            ],

            'response' => [
                'body' => $safeRespBody,
            ],

            'error' => $log->error_class ? [
                'class'   => (string) $log->error_class,
                'message' => (string) ($log->error_message ?? ''),
            ] : null,

            'meta' => is_array($log->meta ?? null) ? $log->meta : [],
        ];
    }
}
