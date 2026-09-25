<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use PHPUnit\Framework\TestCase;

/**
 * Payeer rails have no exact BestChange market — substitutions are forbidden.
 */
final class PayeerNoBcRailPolicyTest extends TestCase
{
    private function policyPath(): string
    {
        return dirname(__DIR__, 3).'/resources/rates/payeer-no-bc-rail-policy.json';
    }

    private function overridesPath(): string
    {
        return dirname(__DIR__, 3).'/resources/rates/bestchange-codes.overrides.json';
    }

    public function test_policy_lists_payeer_codes_and_forbidden_substitutions(): void
    {
        $pol = json_decode((string) file_get_contents($this->policyPath()), true);
        $this->assertIsArray($pol);
        $this->assertSame(['PRRUB', 'PRUSD', 'PREUR'], $pol['local_codes'] ?? null);
        $this->assertSame('DERIVED_MARKET_BASELINE', $pol['required_ownership'] ?? null);
        $this->assertSame(0, (int) ($pol['max_active_wrong_bc_links'] ?? -1));

        $forbid = $pol['forbidden_bc_substitutions'] ?? [];
        $this->assertArrayHasKey('PRRUB', $forbid);
        $this->assertArrayHasKey('PRUSD', $forbid);
        $this->assertArrayHasKey('PREUR', $forbid);

        $prrubCodes = array_column($forbid['PRRUB'], 'bc_code');
        $prusdCodes = array_column($forbid['PRUSD'], 'bc_code');
        $preurCodes = array_column($forbid['PREUR'], 'bc_code');
        $this->assertContains('CARDARS', $prrubCodes);
        $this->assertContains('CARDVND', $prusdCodes);
        $this->assertContains('PMUSD', $prusdCodes);
        $this->assertContains('ZENEUR', $preurCodes);
        $this->assertContains('ADVCEUR', $preurCodes);
    }

    public function test_overrides_mark_payeer_absent_and_forbid_poison_ids(): void
    {
        $ov = json_decode((string) file_get_contents($this->overridesPath()), true);
        $absent = $ov['absent_local_codes'] ?? [];
        $this->assertArrayHasKey('PRRUB', $absent);
        $this->assertArrayHasKey('PRUSD', $absent);
        $this->assertArrayHasKey('PREUR', $absent);

        $corr = $ov['id_corrections'] ?? [];
        $this->assertSame('CARDVND', $corr['108']['code'] ?? null);
        $this->assertSame('CARDARS', $corr['117']['code'] ?? null);
        $this->assertSame('ZENEUR', $corr['122']['code'] ?? null);
    }

    public function test_is_forbidden_substitution_helper(): void
    {
        $pol = json_decode((string) file_get_contents($this->policyPath()), true);
        $this->assertTrue($this->isForbidden($pol, 'PRRUB', 117, 'CARDARS'));
        $this->assertTrue($this->isForbidden($pol, 'PRUSD', 108, 'CARDVND'));
        $this->assertTrue($this->isForbidden($pol, 'PRUSD', null, 'PMUSD'));
        $this->assertTrue($this->isForbidden($pol, 'PREUR', 122, 'ZENEUR'));
        $this->assertFalse($this->isForbidden($pol, 'PRRUB', 99999, 'SOMEOTHER'));
    }

    /**
     * @param array<string,mixed> $pol
     */
    private function isForbidden(array $pol, string $local, ?int $bcId, ?string $bcCode): bool
    {
        foreach (($pol['forbidden_bc_substitutions'][$local] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ($bcId !== null && isset($row['bc_id']) && (int) $row['bc_id'] === $bcId) {
                return true;
            }
            if ($bcCode !== null && isset($row['bc_code']) && strtoupper((string) $row['bc_code']) === strtoupper($bcCode)) {
                return true;
            }
        }

        return false;
    }
}
