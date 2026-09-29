<?php

declare(strict_types=1);

namespace Tests\Unit\Orders;

use App\Models\Task;
use App\Services\Orders\Archival\OwnerConfirmedHistoricalSettlement;
use App\Services\Orders\ManualCompletion\ManualCompletionException;
use App\Services\Orders\ManualCompletion\ManualCompletionGuard;
use App\Services\Orders\Reconciliation\FundedOrderHealthBuilder;
use App\Services\Orders\Reconciliation\FundedReconciliationClassifier;
use Tests\TestCase;

final class OwnerConfirmedStatus7ArchiveTest extends TestCase
{
    public function test_reference_is_deterministic(): void
    {
        $this->assertSame(
            'historical-status7-owner-settlement-20260929:42',
            OwnerConfirmedHistoricalSettlement::referenceForOrderId(42)
        );
    }

    public function test_guard_blocks_system_completion_when_owner_attested(): void
    {
        $task = new Task();
        $task->status = 7;
        $task->opt_params = [
            OwnerConfirmedHistoricalSettlement::OPT_KEY => OwnerConfirmedHistoricalSettlement::buildPayload(
                1,
                'test',
                '2026-09-29T00:00:00+00:00'
            ),
        ];

        try {
            (new ManualCompletionGuard())->authorizeLockedTask(
                $task,
                ManualCompletionGuard::SOURCE_SYSTEM,
                ['system_writer' => 'test'],
                null
            );
            $this->fail('expected lock');
        } catch (ManualCompletionException $e) {
            $this->assertSame('owner_confirmed_historical_settlement', $e->errorCode);
            $this->assertSame(409, $e->httpStatus);
        }
    }

    public function test_health_builder_excludes_owner_attested_from_active_backlog(): void
    {
        $rows = [
            [
                'current_status' => 7,
                'classification' => 'FUNDED_AWAITING_MANUAL_SETTLEMENT',
                'age_hours' => 10,
                'is_bot' => 0,
                'inbound_evidence' => FundedReconciliationClassifier::CONFIRMED_FUNDS,
                'active_money_at_risk' => 1,
            ],
            [
                'current_status' => 7,
                'classification' => FundedReconciliationClassifier::OWNER_CONFIRMED_HISTORICAL_SETTLEMENT,
                'age_hours' => 1000,
                'is_bot' => 0,
                'inbound_evidence' => FundedReconciliationClassifier::CONFIRMED_FUNDS,
                'active_money_at_risk' => 0,
            ],
        ];

        $health = (new FundedOrderHealthBuilder())->build($rows, ['state' => 'OK']);
        $this->assertSame(1, $health['active_status7_backlog']);
        $this->assertSame(1, $health['historical_owner_confirmed_settled']);
        $this->assertSame(1, $health['active_money_at_risk_count']);
        $this->assertSame(2, $health['paid_total_including_owner_attested']);
    }
}
