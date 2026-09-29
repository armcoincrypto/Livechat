<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Enums\TaskStatusEnum;
use App\Models\Task;
use App\Services\Orders\Transitions\FailClosedPaymentDestinationTransition;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fail-closed after order persist: destination-required rails must not stay
 * customer-payable without a persisted deposit destination.
 *
 * Remote provider calls happen outside a DB transaction. Status is moved off
 * PENDING_PAYMENT before the issue attempt so a killed/timed-out request cannot
 * leave a payable order (order 5088 class of defect).
 *
 * Status mutations go through FailClosedPaymentDestinationTransition (canonical
 * contract) — never Transaction::setStatus (would reset reserves / recount on
 * temporary park) and never ad-hoc forceFill(status).
 */
final class PaymentDestinationFailClosedGuard
{
    public const FAILURE_EMPTY = 'empty_or_missing_destination';

    public const FAILURE_EXCEPTION = 'provider_or_issue_exception';

    public function __construct(
        private readonly FailClosedPaymentDestinationTransition $failClosedTransition = new FailClosedPaymentDestinationTransition(),
    ) {}

    /**
     * @return array{reject: bool, restore_pending: bool}
     */
    public static function decide(string $owner, bool $hasAssignedDestination): array
    {
        if (! self::destinationRequired($owner)) {
            return ['reject' => false, 'restore_pending' => false];
        }

        if ($hasAssignedDestination) {
            return ['reject' => false, 'restore_pending' => true];
        }

        return ['reject' => true, 'restore_pending' => false];
    }

    public static function destinationRequired(string $owner): bool
    {
        return $owner === PaymentDestinationRouter::OWNER_KOBBOPAY;
    }

    public static function mustNotRemainPayable(string $owner, bool $hasAssignedDestination): bool
    {
        return self::decide($owner, $hasAssignedDestination)['reject'] === true;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    public function enforceAfterCreate(Task $order): ?array
    {
        $order->loadMissing(['direction_exchange.currency1']);
        $direction = $order->direction_exchange;
        if ($direction === null) {
            return null;
        }

        $owner = PaymentDestinationRouter::classifyDirection($direction);
        $required = self::destinationRequired($owner);
        $exceptionClass = null;

        if ($required && (int) $order->status === TaskStatusEnum::PENDING_PAYMENT->value) {
            // Not payable during the remote issue attempt (timeout-safe).
            $this->failClosedTransition->parkNotPayable($order);
        }

        Log::info('payment_destination_issue_attempt', [
            'task_id' => $order->id,
            'public_id' => $order->public_id,
            'owner' => $owner,
            'direction_id' => $direction->id ?? null,
            'merchant_id' => $order->id_merchant ?? null,
            'provider' => $owner,
        ]);

        try {
            $ctx = app(\iEXPackages\Order\Services\InvoiceContextService::class)->resolve($order);
        } catch (Throwable $e) {
            $exceptionClass = $e::class;
            Log::error('payment_destination_issue_failed', [
                'task_id' => $order->id,
                'public_id' => $order->public_id,
                'owner' => $owner,
                'direction_id' => $direction->id ?? null,
                'merchant_id' => $order->id_merchant ?? null,
                'provider' => $owner,
                'failure_class' => self::FAILURE_EXCEPTION,
                'exception' => $exceptionClass,
                'message' => $e->getMessage(),
            ]);
        }

        $order->refresh();
        $hasDest = InboundPaymentDestinationGuard::taskHasAssignedDestination($order);
        $decision = self::decide($owner, $hasDest);

        if (! $required) {
            return null;
        }

        if ($decision['restore_pending'] === true) {
            $this->failClosedTransition->restorePending($order);
            if ($owner === PaymentDestinationRouter::OWNER_KOBBOPAY) {
                Log::info('kobbopay_destination_request_success', [
                    'task_id' => $order->id,
                    'direction_id' => $direction->id ?? null,
                    'letter_cod' => (string) ($direction->currency1?->designation_xml ?? ''),
                ]);
            }

            return null;
        }

        Log::warning('payment_destination_fail_closed', [
            'task_id' => $order->id,
            'public_id' => $order->public_id,
            'owner' => $owner,
            'direction_id' => $direction->id ?? null,
            'merchant_id' => $order->id_merchant ?? null,
            'provider' => $owner,
            'failure_class' => $exceptionClass !== null ? self::FAILURE_EXCEPTION : self::FAILURE_EMPTY,
            'exception' => $exceptionClass,
        ]);

        Log::warning('payment_destination_unavailable', [
            'task_id' => $order->id,
            'public_id' => $order->public_id,
            'owner' => $owner,
            'letter_cod' => (string) ($direction->currency1?->designation_xml ?? ''),
        ]);

        if ((int) $order->status === TaskStatusEnum::PENDING_PAYMENT->value) {
            try {
                $this->failClosedTransition->ensureRejected($order);
            } catch (Throwable $e) {
                Log::error('order_abandon_status_failed', [
                    'task_id' => $order->id,
                    'message' => $e->getMessage(),
                ]);
            }
        } elseif ((int) $order->status === TaskStatusEnum::REJECTED->value) {
            // Already parked pre-issue; confirm path is a noop for status.
            $this->failClosedTransition->ensureRejected($order);
        }

        return [[
            'field' => 'direction',
            'message' => __('Приём средств по этому направлению временно недоступен'),
            'code' => InboundPaymentDestinationGuard::ERROR_PAYMENT_DESTINATION_UNAVAILABLE,
            'modal' => false,
            'meta' => [],
        ]];
    }
}
