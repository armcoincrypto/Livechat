<?php

declare(strict_types=1);

namespace App\Services\Certification;

use App\Enums\TaskStatusEnum;
use App\Models\DirectionExchange;
use App\Models\Task;
use App\Services\Orders\InboundPaymentDestinationGuard;
use App\Services\Orders\PaymentDestinationRouter;
use iEXPackages\Order\Facades\OrderFacade;
use iEXPackages\Order\OrderCalculator;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Operator-only lifecycle driver. Never exposes a public route.
 */
final class CertificationLifecycleRunner
{
    public function __construct(
        private readonly CertificationGate $gate,
        private readonly CertificationContext $context,
        private readonly CertificationPaymentSimulator $payments,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function run(string $scenario, string $email, string $token): array
    {
        $this->context->activate($scenario, $email, $token);
        try {
            $gate = $this->gate->evaluate();
            if ($gate['allowed'] !== true) {
                throw new RuntimeException(CertificationGate::REJECTED.' '.$gate['reason']);
            }

            $quote = $this->quote();
            $create = $this->createOrder($quote, $email);
            $order = $create['order'];

            $result = [
                'QUOTE' => 'PASS',
                'ORDER_CREATE' => $order instanceof Task ? 'PASS' : 'FAIL',
                'scenario' => $scenario,
                'order_id' => $order?->id,
                'public_id' => $order?->public_id,
                'status' => $order?->status,
                'REAL_KOBBOPAY_HTTP_CALL_COUNT' => $this->context->kobbopayHttpCallCount(),
                'mail' => $this->context->mailRecords(),
                'telegram' => $this->context->telegramRecords(),
            ];

            if ($scenario === CertificationContext::SCENARIO_FAIL_CLOSED) {
                $result['DESTINATION'] = InboundPaymentDestinationGuard::taskHasAssignedDestination($order) ? 'FAIL' : 'PASS';
                $result['PAYABLE_STATE_WITHOUT_DESTINATION'] = (int) $order->status === TaskStatusEnum::PENDING_PAYMENT->value
                    ? 'YES'
                    : 'NO';
                $result['ORDER_STATUS'] = (int) $order->status === TaskStatusEnum::REJECTED->value
                    ? 'REJECTED_OR_EXISTING_FAIL_CLOSED_STATE'
                    : (string) $order->status;
                $result['CUSTOMER_MAIL'] = $this->mailSent() ? 'FAIL' : 'PASS';
                $result['OPERATOR_TELEGRAM'] = $this->telegramSent() ? 'FAIL' : 'PASS';

                return $result;
            }

            $result['DESTINATION'] = InboundPaymentDestinationGuard::taskHasAssignedDestination($order) ? 'PASS' : 'FAIL';
            $result['CUSTOMER_MAIL'] = $this->mailEligible() ? 'PASS' : 'FAIL';
            $result['OPERATOR_TELEGRAM'] = $this->telegramSent() ? 'PASS' : 'FAIL';

            $paid = $this->payments->emitPaid($order);
            $result['PAYMENT_DETECTION'] = $paid['outcome'] === 'paid' || $paid['outcome'] === 'already_paid' ? 'PASS' : 'FAIL';
            $result['PAID_TRANSITION'] = (int) $order->fresh()->status === TaskStatusEnum::PAID->value ? 'PASS' : 'FAIL';
            $result['PROCESSING'] = (int) $paid['from_status'] === TaskStatusEnum::WAITING_HANDLE->value ? 'PASS' : 'FAIL';

            $dup = $this->payments->emitPaid($order->fresh());
            $result['IDEMPOTENCY'] = $dup['outcome'] === 'already_paid' ? 'PASS' : 'FAIL';

            $this->payments->complete($order->fresh());
            $completed = (int) $order->fresh()->status === TaskStatusEnum::COMPLETED->value;
            $result['COMPLETION'] = $completed ? 'PASS' : 'FAIL';
            $replay = $this->payments->complete($order->fresh());
            if (empty($replay['already_completed']) && (int) $order->fresh()->status !== TaskStatusEnum::COMPLETED->value) {
                $result['IDEMPOTENCY'] = 'FAIL';
            }

            $fresh = $order->fresh();
            $result['ACCOUNT_HISTORY'] = Task::query()->where('id_user', $fresh->id_user)->whereKey($fresh->id)->exists()
                ? 'PASS'
                : 'FAIL';
            $result['ADMIN_HISTORY'] = Task::query()->whereKey($fresh->id)->exists() ? 'PASS' : 'FAIL';
            $result['REAL_KOBBOPAY_HTTP_CALL_COUNT'] = $this->context->kobbopayHttpCallCount();

            return $result;
        } finally {
            $this->context->deactivate();
        }
    }

    /**
     * @return array{direction:DirectionExchange, income_amount:float, outcome_amount:float, income_id:int, outcome_id:int}
     */
    public function quote(): array
    {
        $direction = DirectionExchange::query()
            ->with(['currency1', 'currency2'])
            ->where('status', 1)
            ->where('id_currency1', 3)
            ->where('id_currency2', 66)
            ->first();

        if ($direction === null) {
            $direction = DirectionExchange::query()
                ->with(['currency1', 'currency2'])
                ->where('status', 1)
                ->whereHas('currency1', function ($q): void {
                    $q->whereIn('designation_xml', PaymentDestinationRouter::KOBBOPAY_USDT_LETTER_CODS);
                })
                ->orderBy('id')
                ->first();
        }

        if ($direction === null) {
            throw new RuntimeException('No quoteable Kobbopay inbound direction');
        }

        $min = (float) ($direction->min_amount1 ?? $direction->currency1->min_amount ?? 1);
        $amount = max($min, 200.0);
        $calc = (new OrderCalculator($direction))
            ->setFromAmount($amount)
            ->run();

        return [
            'direction' => $direction,
            'income_amount' => (float) ($calc['give_price'] ?? $amount),
            'outcome_amount' => (float) ($calc['receiving_price'] ?? 0),
            'income_id' => (int) $direction->id_currency1,
            'outcome_id' => (int) $direction->id_currency2,
        ];
    }

    /**
     * @param  array{direction:DirectionExchange, income_amount:float, outcome_amount:float, income_id:int, outcome_id:int}  $quote
     * @return array{order:Task, raw:array<string, mixed>}
     */
    private function createOrder(array $quote, string $email): array
    {
        $payload = [
            'income_payment_system' => $quote['income_id'],
            'outcome_payment_system' => $quote['outcome_id'],
            'income_amount' => $quote['income_amount'],
            'outcome_amount' => $quote['outcome_amount'],
            'email' => $email,
            'outcome_account' => '4400430000000001',
            'agree_rules' => 1,
            'fields_in' => [
                'income_outcome_income_vas_telegramm_whatsapp_1' => '@qa_exswaping_cert',
            ],
            'fields_out' => [
                'outcome_nomer_karty' => '4400430000000001',
                'sender_fullname' => 'QA Certification',
            ],
        ];

        $request = Request::create('/client-api/v1/order', 'POST', $payload);
        $request->headers->set('Accept', 'application/json');
        $request->headers->set((string) config('exs_order_certification.request_header'), '1');
        $request->headers->set((string) config('exs_order_certification.token_header'), $this->context->token());

        $orderService = OrderFacade::request($request);
        $response = $orderService->created();

        $order = null;
        if (isset($response['data']['public_id'])) {
            $order = Task::query()->where('public_id', $response['data']['public_id'])->first();
        }
        if ($order === null && $this->context->orderId() !== null) {
            $order = Task::query()->find($this->context->orderId());
        }
        if (! $order instanceof Task) {
            throw new RuntimeException('certification order was not persisted: '.json_encode($response));
        }

        if (! CertificationOrderMarker::isMarked($order)) {
            CertificationOrderMarker::apply($order, $this->context->scenario());
        }

        return ['order' => $order, 'raw' => $response];
    }

    private function mailSent(): bool
    {
        foreach ($this->context->mailRecords() as $row) {
            if ($row['eligible'] === true) {
                return true;
            }
        }

        return false;
    }

    private function mailEligible(): bool
    {
        return $this->mailSent();
    }

    private function telegramSent(): bool
    {
        foreach ($this->context->telegramRecords() as $row) {
            if ($row['sent'] === true) {
                return true;
            }
        }

        return false;
    }
}
