<?php

declare(strict_types=1);

namespace Tests\Unit\Orders;

use Tests\TestCase;

/**
 * Source-contract pins for /order create idempotency.
 *
 * Does not change IP-binding semantics. Guest reuse still requires matching
 * client IP. FUTURE_DESIGN_REVIEW: whether a new order_attempt_id should skip
 * the DB pending-row fallback (hash already includes attempt; DB fallback does not).
 */
final class OrderCreateIdempotencyContractTest extends TestCase
{
    private function managerOrderSource(): string
    {
        $path = dirname(__DIR__, 3).'/packages/Order/Bindings/ManagerOrder.php';
        $this->assertFileExists($path);
        $src = (string) file_get_contents($path);
        $this->assertNotSame('', $src);

        return $src;
    }

    public function test_same_attempt_id_is_cached_for_reuse(): void
    {
        $src = $this->managerOrderSource();
        $this->assertStringContainsString("Cache::put('order_create_attempt:v1:'.\$attempt, \$order->id", $src);
        $this->assertStringContainsString("Cache::get('order_create_attempt:v1:'.\$attempt)", $src);
        $this->assertStringContainsString("'via' => 'attempt_id'", $src);
    }

    public function test_dedup_hash_includes_attempt_so_duplicate_click_with_same_attempt_collapses(): void
    {
        $src = $this->managerOrderSource();
        $this->assertStringContainsString("'attempt' => \$this->normalizedOrderAttemptId()", $src);
        $this->assertStringContainsString('order_create_idem:v1:', $src);
    }

    public function test_new_attempt_id_changes_hash_but_db_fallback_still_matches_pending_guest_row(): void
    {
        $src = $this->managerOrderSource();
        $this->assertStringContainsString('function tryReuseRecentPendingTask()', $src);
        $this->assertStringContainsString("->where('status', TaskStatusEnum::PENDING_PAYMENT->value)", $src);
        $this->assertStringContainsString("->where('ip', \$this->clientIp())", $src);
        $this->assertStringContainsString("'via' => 'db'", $src);
    }

    public function test_guest_reuse_requires_matching_email_and_client_ip(): void
    {
        $src = $this->managerOrderSource();
        $this->assertStringContainsString('function taskMatchesReuseContract(', $src);
        $this->assertStringContainsString(
            'if ($tEmail !== $email || (string) $task->ip !== (string) $this->clientIp())',
            $src
        );
    }
}
