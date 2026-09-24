<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use PHPUnit\Framework\TestCase;

/**
 * Legacy catalog-close protections may be lifted only for certified SAFE_TO_MIGRATE
 * IDs. GRAM/TON poison guards, Zelle, and review/thin rows stay blocked.
 *
 * Guard runtime cache is not asserted here: fromStorageApp() may resolve the
 * live release JSON during isolated PHPUnit, while this file asserts the
 * canonical worktree config that will be deployed.
 */
final class ProtectedDerivedLegacyUnprotectTest extends TestCase
{
    /**
     * Legacy poison peers that remain derived-owned with block_bestchange_overwrite.
     * Wave-1 GRAM/TON IDs (incl. 421) are ABSENT from derived registry — see
     * GramBestChangeMigratedRegistryInvariantTest.
     */
    private const MUST_STAY_PROTECTED = [11];

    /** High-liquidity crypto-crypto canaries from the 2026-08-25 protected audit. */
    private const CANARIES_UNPROTECTED = [1573, 1523, 2877, 2802, 194, 159, 172, 569];

    /** Coin→ZELLEUSD derived pegs must never become BestChange. */
    private const ZELLE_RECEIVE_STILL_PROTECTED = [3134];

    public function test_gram_poison_guards_remain_blocked(): void
    {
        $cfg = $this->directions();
        foreach (self::MUST_STAY_PROTECTED as $id) {
            $this->assertTrue(
                !empty($cfg[(string) $id]['ownership']['block_bestchange_overwrite']),
                "direction {$id} must remain block_bestchange_overwrite"
            );
        }
    }

    public function test_certified_canaries_are_unprotected(): void
    {
        $cfg = $this->directions();
        foreach (self::CANARIES_UNPROTECTED as $id) {
            $this->assertArrayHasKey((string) $id, $cfg, "canary {$id} must remain derived-owned");
            $this->assertFalse(
                !empty($cfg[(string) $id]['ownership']['block_bestchange_overwrite']),
                "canary {$id} must not keep legacy block_bestchange_overwrite"
            );
        }
    }

    public function test_zelle_receive_and_thin_review_stay_protected(): void
    {
        $cfg = $this->directions();
        foreach (self::ZELLE_RECEIVE_STILL_PROTECTED as $id) {
            $this->assertArrayHasKey(
                (string) $id,
                $cfg,
                "Zelle receive {$id} must remain derived-registry owned (never BestChange catalog)"
            );
        }
        // Thin NEO INR rails from the protected audit.
        foreach ([2917, 2919] as $id) {
            $this->assertTrue(
                !empty($cfg[(string) $id]['ownership']['block_bestchange_overwrite']),
                "thin {$id} must stay protected"
            );
        }
    }

    public function test_derived_apply_still_hard_skips_active_bestchange(): void
    {
        $src = (string) file_get_contents(
            dirname(__DIR__, 3) . '/app/Services/Rates/DerivedMarketBaselineAuthority.php'
        );
        $this->assertStringContainsString("'skipped' => 'bestchange_active'", $src);
        $this->assertStringContainsString('BestChangeMarketBaseHealth::hasActiveLink($directionId)', $src);
        $this->assertStringContainsString('ZelleOutgoingRateWriteGuard::denyGenericWrite', $src);
    }

    /** @return array<string,mixed> */
    private function directions(): array
    {
        $path = dirname(__DIR__, 3) . '/resources/rates/derived-market-baseline-directions.json';
        $json = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($json);
        $cfg = $json['directions'] ?? [];
        $this->assertIsArray($cfg);

        return $cfg;
    }
}
