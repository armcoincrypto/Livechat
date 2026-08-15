<?php

declare(strict_types=1);

namespace Tests\Unit\Orders;

use App\Services\Orders\Reconciliation\FundedReconciliationClassifier;
use Tests\TestCase;

final class FundedReconciliationClassifierTest extends TestCase
{
    public function test_paid_confirmed_is_awaiting_manual_settlement(): void
    {
        $row = $this->row(['status' => 7, 'has_merchant' => 1, 'has_wallet' => 1, 'wallet_txid' => 'abc123']);
        $this->assertSame('CONFIRMED_FUNDS', FundedReconciliationClassifier::inboundStrength($row));
        $this->assertSame('FUNDED_AWAITING_MANUAL_SETTLEMENT', FundedReconciliationClassifier::classification($row));
    }

    public function test_bot_flag_does_not_drop_confirmed_funds(): void
    {
        $row = $this->row(['status' => 7, 'is_bot' => 1, 'has_wallet' => 1, 'wallet_txid' => 'abc123']);
        $this->assertSame('BOT_FLAGGED_BUT_FUNDED', FundedReconciliationClassifier::classification($row));
        $this->assertSame('BOT_FLAGGED_BUT_FUNDED', FundedReconciliationClassifier::operatorPriorityGroup($row, 'BOT_FLAGGED_BUT_FUNDED'));
    }

    public function test_waiting_handle_with_txid_is_wrong_status(): void
    {
        $row = $this->row(['status' => 3, 'has_wallet' => 1, 'wallet_txid' => 'abc123', 'age_hours' => 10]);
        $this->assertSame('FUNDED_WRONG_STATUS', FundedReconciliationClassifier::classification($row));
        $this->assertSame('CONFIRMED_FUNDS_WRONG_STATUS', FundedReconciliationClassifier::waitingHandleDetail($row));
    }

    public function test_status_is_not_proof_without_evidence(): void
    {
        $row = $this->row(['status' => 7]);
        $this->assertSame('NO_EVIDENCE', FundedReconciliationClassifier::inboundStrength($row));
        $this->assertSame('AMBIGUOUS', FundedReconciliationClassifier::classification($row));
    }

    public function test_old_waiting_handle_without_funds_is_expired_candidate(): void
    {
        $row = $this->row(['status' => 3, 'age_hours' => 100]);
        $this->assertSame('EXPIRED_NO_FUNDS', FundedReconciliationClassifier::classification($row));
    }

    public function test_historical_completed_without_wave1_evidence(): void
    {
        $row = $this->row(['status' => 4]);
        $this->assertSame('HISTORICAL_LEGACY_COMPLETE', FundedReconciliationClassifier::classification($row));
    }

    public function test_wave1_completed_is_consistent(): void
    {
        $row = $this->row(['status' => 4, 'manual_settlement' => 'SEPA-1']);
        $this->assertSame('COMPLETED_CONSISTENT', FundedReconciliationClassifier::classification($row));
    }

    public function test_expired_with_funds_not_discardable(): void
    {
        $row = $this->row(['status' => 1, 'has_wallet' => 1, 'wallet_txid' => 'abc123']);
        $this->assertSame('EXPIRED_WITH_FUNDS', FundedReconciliationClassifier::classification($row));
        $this->assertSame('review_do_not_discard_funds', FundedReconciliationClassifier::recommendedAction('EXPIRED_WITH_FUNDS'));
    }

    public function test_merchant_checkout_row_is_not_confirmed_funds(): void
    {
        $row = $this->row(['status' => 3, 'has_merchant' => 1, 'age_hours' => 10]);
        $this->assertSame('CUSTOMER_CLAIM_ONLY', FundedReconciliationClassifier::inboundStrength($row));
        $this->assertSame('CUSTOMER_CLAIM_ONLY', FundedReconciliationClassifier::classification($row));
    }

    private function row(array $over = []): array
    {
        return array_merge([
            'status' => 3,
            'is_bot' => 0,
            'age_hours' => 1,
            'has_merchant' => 0,
            'has_wallet' => 0,
            'wallet_txid' => '',
            'wallet_amount' => null,
            'give_price' => 100,
            'has_pays' => 0,
            'has_autosender' => 0,
            'has_wallets_history' => 0,
            'has_webhook' => 0,
            'in_amount_merchant' => null,
            'duplicate_inbound' => 0,
            'manual_settlement' => null,
            'created_at' => '2026-08-01 00:00:00',
            'inbound_confirmed_at' => null,
        ], $over);
    }
}
