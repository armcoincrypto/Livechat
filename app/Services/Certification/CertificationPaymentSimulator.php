<?php

declare(strict_types=1);

namespace App\Services\Certification;

use App\Enums\TaskStatusEnum;
use App\Models\Task;
use App\Services\Orders\InboundPaymentDestinationGuard;
use App\Services\Orders\Transitions\ConfirmedInboundPaidTransition;
use iEXPackages\Transaction\Facades\TransactionFacade;
use RuntimeException;

/**
 * Synthetic inbound payment. Never polls Kobbopay or accepts a provider webhook.
 * Completes by status write only — never ManagersSuccess (treasury/payout/accounting).
 */
final class CertificationPaymentSimulator
{
    public function __construct(
        private readonly ConfirmedInboundPaidTransition $paidTransition,
    ) {
    }

    /**
     * @return array{outcome:string, from_status:int, to_status:int}
     */
    public function emitPaid(Task $order): array
    {
        if (! CertificationOrderMarker::isMarked($order)) {
            throw new RuntimeException('CERTIFICATION_REQUEST=REJECTED not a certification order');
        }
        if (! InboundPaymentDestinationGuard::taskHasAssignedDestination($order)) {
            throw new RuntimeException('CERTIFICATION_REQUEST=REJECTED no destination');
        }

        $order->refresh();
        if ((int) $order->status === TaskStatusEnum::PENDING_PAYMENT->value) {
            TransactionFacade::init($order)->setStatus(TaskStatusEnum::WAITING_HANDLE->value);
            $order->refresh();
        }

        $txid = 'QA_CERT_TX_'.$order->id;

        return $this->paidTransition->apply((int) $order->id, [
            'amount' => $order->give_price,
            'confirmations' => 1,
            'txid' => $txid,
            'address' => (string) $order->transfer_to_account,
        ], function () use ($order): void {
            $tx = TransactionFacade::init($order->fresh());
            $tx->grantPaidWritePermit();
            $tx->setStatus(TaskStatusEnum::PAID->value);
        });
    }

    /**
     * @return array{ok:bool, already_completed?:bool}
     */
    public function complete(Task $order): array
    {
        if (! CertificationOrderMarker::isMarked($order)) {
            throw new RuntimeException('CERTIFICATION_REQUEST=REJECTED not a certification order');
        }

        $order->refresh();
        if ((int) $order->status === TaskStatusEnum::COMPLETED->value) {
            return ['ok' => true, 'already_completed' => true];
        }

        $tx = TransactionFacade::init($order);
        $tx->grantCompleteWritePermit();
        $tx->setStatus(TaskStatusEnum::COMPLETED->value);

        $order->refresh();
        if ((int) $order->status !== TaskStatusEnum::COMPLETED->value) {
            throw new RuntimeException('certification completion did not persist');
        }

        return ['ok' => true];
    }
}
