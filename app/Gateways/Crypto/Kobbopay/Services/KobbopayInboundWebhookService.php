<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Kobbopay\Services;

use App\Enums\TaskStatusEnum;
use App\Models\GatewayMerchant;
use App\Models\MerchantTransactionData;
use App\Models\ProviderWebhookEvent;
use App\Models\Task;
use iEXPackages\Payments\Logging\Services\MerchantFlowLogger;
use iEXPackages\Transaction\Facades\TransactionFacade;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Dedicated Kobbopay inbound webhook handler on the existing callbacks/v1/webhook route.
 *
 * Reuses TransactionFacade::checkInPayment() for confirmation events (polling success path).
 */
final class KobbopayInboundWebhookService
{
    public const PROVIDER = 'kobbopay';

    private const CONFIRM_EVENTS = [
        'payment.confirmed',
        'payment.paid',
    ];

    private const ELIGIBLE_EXPIRE_STATUSES = [
        TaskStatusEnum::PENDING_PAYMENT->value,
        TaskStatusEnum::PROCESSING_PAYMENT->value,
        TaskStatusEnum::CHECK_PAYMENT->value,
        TaskStatusEnum::MERCHANT_CONFIRMATION->value,
    ];

    private const TERMINAL_STATUSES = [
        TaskStatusEnum::EXPIRED->value,
        TaskStatusEnum::COMPLETED->value,
        TaskStatusEnum::REJECTED->value,
        TaskStatusEnum::CANCELED_BY_USER->value,
        TaskStatusEnum::PAID->value,
        TaskStatusEnum::DELETED->value,
    ];

    public function __construct(
        private readonly KobbopayWebhookSignatureVerifier $verifier,
        private readonly KobbopayWebhookSecretResolver $secretResolver,
        private readonly MerchantFlowLogger $flowLogger,
    ) {}

    public function handleHttp(Request $request, string $securityHashFromUrl = ''): Response
    {
        $rawBody = $request->getContent() ?? '';
        $headers = [];
        foreach ($request->headers->all() as $key => $values) {
            $headers[$key] = is_array($values) ? ($values[0] ?? '') : $values;
        }

        $merchant = GatewayMerchant::query()->where('alias', self::PROVIDER)->first();
        $secret = $this->secretResolver->resolve($merchant);
        if ($secret === null) {
            Log::warning('kobbopay_webhook_secret_unresolved', [
                'delivery_id' => $headers[KobbopayWebhookSignatureVerifier::HEADER_DELIVERY_ID] ?? null,
            ]);

            return $this->json(503, ['accepted' => false, 'error' => 'secret_unresolved']);
        }

        $verify = $this->verifier->verify($rawBody, $headers, $secret);
        if (!($verify['ok'] ?? false)) {
            $error = (string) ($verify['error'] ?? 'invalid_signature');
            $status = match ($error) {
                'missing_headers', 'malformed_timestamp' => 400,
                'stale_timestamp', 'invalid_signature', 'missing_secret' => 401,
                default => 401,
            };

            Log::warning('kobbopay_webhook_signature_rejected', [
                'error' => $error,
                'event_id' => $verify['event_id'] ?? null,
                'delivery_id' => $verify['delivery_id'] ?? null,
            ]);

            return $this->json($status, ['accepted' => false, 'error' => $error]);
        }

        if ($merchant && trim((string) ($merchant->security_hash ?? '')) !== '') {
            $expected = trim((string) $merchant->security_hash);
            if ($securityHashFromUrl === '' || !hash_equals($expected, $securityHashFromUrl)) {
                return $this->json(403, ['accepted' => false, 'error' => 'invalid_hash']);
            }
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return $this->json(400, ['accepted' => false, 'error' => 'invalid_json']);
        }

        $eventId = (string) $verify['event_id'];
        $deliveryId = $verify['delivery_id'] ?? null;
        $eventType = $this->resolveEventType($payload, $eventId);
        $paymentId = $this->extractPaymentId($payload);
        $payloadHash = hash('sha256', $rawBody);

        try {
            $result = $this->processVerifiedEvent(
                merchant: $merchant,
                eventId: $eventId,
                deliveryId: is_string($deliveryId) ? $deliveryId : null,
                eventType: $eventType,
                paymentId: $paymentId,
                payloadHash: $payloadHash,
                payload: $payload,
                ip: (string) $request->ip(),
            );
        } catch (Throwable $e) {
            Log::error('kobbopay_webhook_processing_failed', [
                'event_id' => $eventId,
                'delivery_id' => $deliveryId,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);

            return $this->json(500, ['accepted' => false, 'error' => 'processing_failed']);
        }

        return $this->json((int) $result['http'], $result['body']);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{http:int, body:array<string,mixed>}
     */
    private function processVerifiedEvent(
        ?GatewayMerchant $merchant,
        string $eventId,
        ?string $deliveryId,
        string $eventType,
        ?string $paymentId,
        string $payloadHash,
        array $payload,
        string $ip,
    ): array {
        $existing = ProviderWebhookEvent::query()
            ->where('provider', self::PROVIDER)
            ->where('event_id', $eventId)
            ->first();

        if ($existing) {
            if (!hash_equals((string) $existing->payload_hash, $payloadHash)) {
                $existing->update(['status' => ProviderWebhookEvent::STATUS_CONFLICT]);

                Log::warning('kobbopay_webhook_event_payload_conflict', [
                    'event_id' => $eventId,
                    'delivery_id' => $deliveryId,
                ]);

                return [
                    'http' => 409,
                    'body' => ['accepted' => false, 'error' => 'event_payload_conflict'],
                ];
            }

            return [
                'http' => 200,
                'body' => ['accepted' => true, 'duplicate' => true],
            ];
        }

        try {
            $event = ProviderWebhookEvent::query()->create([
                'provider' => self::PROVIDER,
                'event_id' => $eventId,
                'delivery_id' => $deliveryId,
                'event_type' => $eventType,
                'provider_payment_id' => $paymentId,
                'task_id' => null,
                'payload_hash' => $payloadHash,
                'status' => ProviderWebhookEvent::STATUS_PROCESSING,
            ]);
        } catch (QueryException $e) {
            $again = ProviderWebhookEvent::query()
                ->where('provider', self::PROVIDER)
                ->where('event_id', $eventId)
                ->first();

            if ($again && hash_equals((string) $again->payload_hash, $payloadHash)) {
                return [
                    'http' => 200,
                    'body' => ['accepted' => true, 'duplicate' => true],
                ];
            }

            Log::warning('kobbopay_webhook_event_race_conflict', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);

            return [
                'http' => 409,
                'body' => ['accepted' => false, 'error' => 'event_race_conflict'],
            ];
        }

        $correlation = $this->correlate($payload, $paymentId, $merchant);
        if (($correlation['error'] ?? null) === 'ambiguous') {
            $event->update([
                'status' => ProviderWebhookEvent::STATUS_FAILED,
                'processed_at' => Carbon::now(),
            ]);

            Log::warning('kobbopay_webhook_ambiguous_correlation', [
                'event_id' => $eventId,
                'delivery_id' => $deliveryId,
                'payment_id' => $paymentId,
            ]);

            return [
                'http' => 409,
                'body' => ['accepted' => false, 'error' => 'ambiguous_correlation'],
            ];
        }

        /** @var Task|null $task */
        $task = $correlation['task'] ?? null;
        /** @var MerchantTransactionData|null $mtd */
        $mtd = $correlation['mtd'] ?? null;

        if (!$task) {
            $event->update([
                'status' => ProviderWebhookEvent::STATUS_UNRESOLVED,
                'processed_at' => Carbon::now(),
            ]);

            $this->flowLogger->info(
                event: 'kobbopay_webhook_unresolved',
                task: null,
                merchant: $merchant,
                ctx: [
                    'ip' => $ip,
                    'gateway_alias' => self::PROVIDER,
                    'context' => [
                        'event_id' => $eventId,
                        'event_type' => $eventType,
                        'payment_id' => $paymentId,
                    ],
                ],
                message: 'Kobbopay webhook verified but no Exswaping task matched.',
                stage: 'lookup',
                flow: 'callback'
            );

            // Match existing merchant callback soft-ack policy for unknown targets.
            return [
                'http' => 200,
                'body' => ['accepted' => true, 'unresolved' => true],
            ];
        }

        $event->update(['task_id' => $task->id]);

        if ($paymentId && $mtd) {
            $this->persistPaymentId($mtd, $paymentId);
        }

        DB::transaction(function () use ($event, $task, $mtd, $merchant, $eventType, $payload, $paymentId, $ip): void {
            $locked = Task::query()->whereKey($task->id)->lockForUpdate()->first();
            if (!$locked) {
                $event->update([
                    'status' => ProviderWebhookEvent::STATUS_FAILED,
                    'processed_at' => Carbon::now(),
                ]);

                return;
            }

            $this->applyEvent($locked, $mtd, $merchant, $eventType, $payload, $paymentId, $ip);

            $event->update([
                'status' => ProviderWebhookEvent::STATUS_PROCESSED,
                'processed_at' => Carbon::now(),
            ]);
        });

        return [
            'http' => 200,
            'body' => [
                'accepted' => true,
                'event_type' => $eventType,
                'task_id' => $task->id,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{task?:Task|null, mtd?:MerchantTransactionData|null, error?:string}
     */
    private function correlate(array $payload, ?string $paymentId, ?GatewayMerchant $merchant): array
    {
        $matches = [];

        if ($paymentId !== null && $paymentId !== '') {
            $byPayment = MerchantTransactionData::query()
                ->where('service_name', self::PROVIDER)
                ->where(function ($q) use ($paymentId) {
                    $q->where('id_from_merchant', $paymentId)
                        ->orWhere('ext_data->provider->payment_id', $paymentId)
                        ->orWhere('ext_data->label', $paymentId);
                })
                ->with(['tasks', 'merchant'])
                ->get();

            foreach ($byPayment as $row) {
                if ($row->tasks) {
                    $matches[$row->tasks->id] = ['task' => $row->tasks, 'mtd' => $row];
                }
            }
        }

        $trackerId = $this->extractTrackerId($payload);
        if ($trackerId !== null && $trackerId !== '' && $trackerId !== $paymentId) {
            $byTracker = MerchantTransactionData::query()
                ->where('service_name', self::PROVIDER)
                ->where('id_from_merchant', $trackerId)
                ->with(['tasks', 'merchant'])
                ->get();

            foreach ($byTracker as $row) {
                if ($row->tasks) {
                    $matches[$row->tasks->id] = ['task' => $row->tasks, 'mtd' => $row];
                }
            }
        }

        if ($matches === []) {
            $orderId = $this->extractOrderId($payload);
            if ($orderId !== null && ctype_digit($orderId)) {
                $task = Task::query()->whereKey((int) $orderId)->first();
                if ($task) {
                    $mtd = MerchantTransactionData::query()
                        ->where('id_task', $task->id)
                        ->where('service_name', self::PROVIDER)
                        ->first();

                    $providerOk = strtolower((string) ($task->merchant_provider ?? '')) === self::PROVIDER
                        || ($mtd !== null);

                    if ($providerOk && $mtd) {
                        $matches[$task->id] = ['task' => $task, 'mtd' => $mtd];
                    }
                }
            }
        }

        if (count($matches) > 1) {
            return ['error' => 'ambiguous'];
        }

        if ($matches === []) {
            return ['task' => null, 'mtd' => null];
        }

        $one = array_values($matches)[0];

        if ($merchant && $one['mtd']?->merchant && (int) $one['mtd']->merchant->id !== (int) $merchant->id) {
            // Soft: still allow if alias matches
            if (strtolower((string) $one['mtd']->merchant->alias) !== self::PROVIDER) {
                return ['error' => 'ambiguous'];
            }
        }

        return $one;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyEvent(
        Task $task,
        ?MerchantTransactionData $mtd,
        ?GatewayMerchant $merchant,
        string $eventType,
        array $payload,
        ?string $paymentId,
        string $ip,
    ): void {
        $normalized = strtolower($eventType);

        if (in_array($normalized, self::CONFIRM_EVENTS, true)) {
            if (in_array((int) $task->status, self::TERMINAL_STATUSES, true)
                && (int) $task->status !== TaskStatusEnum::PAID->value
                && (int) $task->status !== TaskStatusEnum::COMPLETED->value) {
                // Already terminal non-paid — do not mutate.
                return;
            }

            if ((int) $task->status === TaskStatusEnum::COMPLETED->value
                || (int) $task->status === TaskStatusEnum::PAID->value) {
                return;
            }

            $transaction = TransactionFacade::init($task);
            $transaction->setCheckAccurateBalance(true);
            $transaction->checkInPayment();

            $this->flowLogger->info(
                event: 'kobbopay_webhook_confirm_processed',
                task: $task->fresh(),
                merchant: $merchant,
                ctx: [
                    'ip' => $ip,
                    'gateway_alias' => self::PROVIDER,
                    'context' => [
                        'event_type' => $normalized,
                        'payment_id' => $paymentId,
                    ],
                ],
                message: 'Kobbopay confirmation webhook applied via checkInPayment.',
                stage: 'process',
                flow: 'callback'
            );

            return;
        }

        if ($normalized === 'payment.expired') {
            if (!in_array((int) $task->status, self::ELIGIBLE_EXPIRE_STATUSES, true)) {
                return;
            }

            $transaction = TransactionFacade::init($task);
            $transaction->setStatus(TaskStatusEnum::EXPIRED->value);

            $this->flowLogger->info(
                event: 'kobbopay_webhook_expired_processed',
                task: $task->fresh(),
                merchant: $merchant,
                ctx: [
                    'ip' => $ip,
                    'gateway_alias' => self::PROVIDER,
                    'context' => ['payment_id' => $paymentId],
                ],
                message: 'Kobbopay payment.expired applied to eligible unpaid task.',
                stage: 'process',
                flow: 'callback'
            );

            return;
        }

        if ($normalized === 'payment.partial_settled' || $normalized === 'payment.partial') {
            if ($mtd) {
                $ext = is_array($mtd->ext_data) ? $mtd->ext_data : [];
                $ext['provider'] = array_merge(
                    is_array($ext['provider'] ?? null) ? $ext['provider'] : [],
                    [
                        'payment_id' => $paymentId ?? ($ext['provider']['payment_id'] ?? null),
                        'partial' => true,
                        'received_amount' => $this->extractReceivedAmount($payload),
                        'updated_at' => Carbon::now()->toIso8601String(),
                    ]
                );
                $mtd->update(['ext_data' => $ext]);
            }

            $received = $this->extractReceivedAmount($payload);
            if ($received !== null && empty($task->in_amount_merchant)) {
                $task->update(['in_amount_merchant' => $received]);
            }

            $this->flowLogger->warning(
                event: 'kobbopay_webhook_partial_recorded',
                task: $task,
                merchant: $merchant,
                ctx: [
                    'ip' => $ip,
                    'gateway_alias' => self::PROVIDER,
                    'context' => [
                        'payment_id' => $paymentId,
                        'received_amount' => $received,
                    ],
                ],
                message: 'Kobbopay partial settlement recorded without auto-completion.',
                stage: 'process',
                flow: 'callback'
            );

            return;
        }

        if ($normalized === 'transaction.created' || $normalized === 'payment.created') {
            if ($mtd && $paymentId) {
                $this->persistPaymentId($mtd, $paymentId);
            }

            $this->flowLogger->info(
                event: 'kobbopay_webhook_created_recorded',
                task: $task,
                merchant: $merchant,
                ctx: [
                    'ip' => $ip,
                    'gateway_alias' => self::PROVIDER,
                    'context' => ['payment_id' => $paymentId, 'event_type' => $normalized],
                ],
                message: 'Kobbopay informational create event recorded; no financial mutation.',
                stage: 'process',
                flow: 'callback'
            );

            return;
        }

        // Unknown verified event — acknowledge, no mutation.
        $this->flowLogger->info(
            event: 'kobbopay_webhook_unknown_ignored',
            task: $task,
            merchant: $merchant,
            ctx: [
                'ip' => $ip,
                'gateway_alias' => self::PROVIDER,
                'context' => ['event_type' => $normalized, 'payment_id' => $paymentId],
            ],
            message: 'Verified Kobbopay event type ignored (no financial mutation).',
            stage: 'process',
            flow: 'callback'
        );
    }

    private function persistPaymentId(MerchantTransactionData $mtd, string $paymentId): void
    {
        $ext = is_array($mtd->ext_data) ? $mtd->ext_data : [];
        $provider = is_array($ext['provider'] ?? null) ? $ext['provider'] : [];
        $provider['payment_id'] = $paymentId;
        $provider['external_id'] = $provider['external_id'] ?? $mtd->id_from_merchant;
        $ext['provider'] = $provider;

        $updates = ['ext_data' => $ext];
        if (trim((string) ($mtd->id_from_merchant ?? '')) === '') {
            $updates['id_from_merchant'] = $paymentId;
        }

        $mtd->update($updates);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveEventType(array $payload, string $eventId): string
    {
        foreach (['event', 'type', 'eventType', 'event_type', 'name'] as $key) {
            $value = Arr::get($payload, $key);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        // Event-Id form: payment:{id}:payment.confirmed
        if (preg_match('/:(payment\.[a-z_]+|transaction\.[a-z_]+)$/', $eventId, $m)) {
            return $m[1];
        }

        return 'unknown';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractPaymentId(array $payload): ?string
    {
        foreach ([
            'paymentId',
            'payment_id',
            'id',
            'data.id',
            'data.paymentId',
            'payment.id',
            'tracker_id',
            'trackerId',
        ] as $path) {
            $value = Arr::get($payload, $path);
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractTrackerId(array $payload): ?string
    {
        foreach (['tracker_id', 'trackerId', 'data.tracker_id'] as $path) {
            $value = Arr::get($payload, $path);
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return $this->extractPaymentId($payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractOrderId(array $payload): ?string
    {
        foreach ([
            'orderId',
            'order_id',
            'client_transaction_id',
            'clientTransactionId',
            'data.orderId',
            'data.client_transaction_id',
        ] as $path) {
            $value = Arr::get($payload, $path);
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractReceivedAmount(array $payload): ?string
    {
        foreach ([
            'receivedAmount',
            'received_amount',
            'amount',
            'data.receivedAmount',
            'data.amount',
            'payment.receivedAmount',
        ] as $path) {
            $value = Arr::get($payload, $path);
            if (is_numeric($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function json(int $status, array $body): Response
    {
        return response()
            ->json($body, $status, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
