<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\ProtectedMarketBaselineWriteGuard;
use Tests\TestCase;

final class ProtectedMarketBaselineWriteGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ProtectedMarketBaselineWriteGuard::clearCache();
    }

    public function test_blocks_gram_usdt_and_trx_gram(): void
    {
        foreach ([11, 131, 427] as $id) { // IDs currently flagged block_bestchange_overwrite in derived registry
            $this->assertTrue(
                ProtectedMarketBaselineWriteGuard::blocksBestchangeOverwrite($id),
                "direction {$id} must be protected from BestChange overwrite"
            );
            $deny = ProtectedMarketBaselineWriteGuard::denyBestchangeWrite($id, 'unit-test');
            $this->assertTrue($deny['blocked']);
            $this->assertStringContainsString('block_bestchange_overwrite', (string) $deny['reason']);
        }
    }

    public function test_non_blocked_id_is_not_denied(): void
    {
        // Synthetic id unlikely to be in derived block list.
        $deny = ProtectedMarketBaselineWriteGuard::denyBestchangeWrite(999999991, 'unit-test');
        $this->assertFalse($deny['blocked']);
    }

    public function test_1419_btc_yamrub_is_not_blocked_after_owner_canary(): void
    {
        $this->assertFalse(
            ProtectedMarketBaselineWriteGuard::blocksBestchangeOverwrite(1419),
            '1419 BTC→YAMRUB was owner-authorized onto BestChange position 5'
        );
    }
}
