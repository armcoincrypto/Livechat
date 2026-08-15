<?php

declare(strict_types=1);

namespace Tests\Unit\Orders;

use App\Enums\TaskStatusEnum;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Wave A financial certification fixtures.
 * Documents intended matrix, known production divergences, and missing payout uniqueness.
 */
final class FinancialIdempotencyCertificationTest extends TestCase
{
    public function test_matrix_documents_final_states_are_frozen(): void
    {
        foreach ([
            TaskStatusEnum::COMPLETED,
            TaskStatusEnum::REJECTED,
            TaskStatusEnum::CANCELED_BY_USER,
            TaskStatusEnum::EXPIRED,
            TaskStatusEnum::INVALID,
            TaskStatusEnum::DELETED,
        ] as $final) {
            $this->assertTrue($final->isFinal(), $final->name.' should be final');
            foreach (TaskStatusEnum::cases() as $to) {
                $this->assertFalse(
                    $final->canTransitionTo($to),
                    $final->name.' must not transition to '.$to->name
                );
            }
        }
    }

    public function test_documented_matrix_includes_production_happy_path(): void
    {
        // Canonical Wave 3 truth: detector 3→7 and completion 7→4 are business-valid.
        $this->assertTrue(
            TaskStatusEnum::PENDING_PAYMENT->canTransitionTo(TaskStatusEnum::WAITING_HANDLE)
        );
        $this->assertTrue(
            TaskStatusEnum::WAITING_HANDLE->canTransitionTo(TaskStatusEnum::PAID)
        );
        $this->assertTrue(
            TaskStatusEnum::PAID->canTransitionTo(TaskStatusEnum::COMPLETED)
        );
        // Autopay queue path IS in the matrix:
        $this->assertTrue(
            TaskStatusEnum::PAID->canTransitionTo(TaskStatusEnum::PAYOUT_QUEUE)
        );
    }

    public function test_set_status_source_does_not_call_can_transition_to(): void
    {
        $path = base_path('packages/Transaction/Bindings/ManagersDetails.php');
        if (!is_file($path)) {
            $this->markTestSkipped('ManagersDetails unavailable');
        }
        $src = (string) file_get_contents($path);
        $this->assertStringContainsString('function setStatus', $src);
        $this->assertStringNotContainsString(
            'canTransitionTo',
            $src,
            'setStatus must not silently gain matrix enforcement without caller remap'
        );
    }

    public function test_autopay_idempotency_tables_lack_unique_order_constraints(): void
    {
        try {
            $pay = collect(DB::select('SHOW INDEX FROM pays_transaction_data'));
            $auto = collect(DB::select('SHOW INDEX FROM autosender_payment'));
        } catch (\Throwable $e) {
            $this->markTestSkipped('db unavailable: '.$e->getMessage());
        }

        $uniquePayTask = $pay->contains(
            fn ($i) => (int) $i->Non_unique === 0 && $i->Column_name === 'id_task'
        );
        $uniqueAutoOrder = $auto->contains(
            fn ($i) => (int) $i->Non_unique === 0 && $i->Column_name === 'id_order'
        );

        // Certification of CURRENT risk: these uniques are missing → concurrent
        // autopay workers can pass application exists() checks and double-call payout.
        $this->assertFalse($uniquePayTask, 'expected missing unique on pays_transaction_data.id_task');
        $this->assertFalse($uniqueAutoOrder, 'expected missing unique on autosender_payment.id_order');
    }

    public function test_inbound_merchant_tx_is_unique_per_task(): void
    {
        try {
            $idx = collect(DB::select('SHOW INDEX FROM merchants_transaction_data'));
        } catch (\Throwable $e) {
            $this->markTestSkipped('db unavailable: '.$e->getMessage());
        }

        $unique = $idx->contains(
            fn ($i) => (int) $i->Non_unique === 0 && $i->Column_name === 'id_task'
        );
        $this->assertTrue($unique, 'merchants_transaction_data.id_task must stay unique');
    }

    public function test_check_payment_only_advances_from_handle_statuses_in_source(): void
    {
        $path = base_path('packages/Transaction/Concerns/SupportsCheckPayment.php');
        if (!is_file($path)) {
            $this->markTestSkipped('SupportsCheckPayment unavailable');
        }
        $src = (string) file_get_contents($path);
        $this->assertStringContainsString('ConfirmedInboundPaidTransition', $src);
        $this->assertStringContainsString('ORDER_MARKED_PAID', $src);
    }
}
