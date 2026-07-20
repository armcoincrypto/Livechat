<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\BestChangeMappingVerifier;
use PHPUnit\Framework\TestCase;

/**
 * GRAM must never be treated as TON; TON/Payeer remain ABSENT → fail-closed for export.
 */
final class ExportMappingFailClosedTest extends TestCase
{
    public function testTonIsAbsentAndGramIsVerifiedSeparately(): void
    {
        $path = base_path('resources/rates/bestchange-codes.overrides.json');
        if (!is_file($path)) {
            $this->markTestSkipped('overrides file unavailable in this environment');
        }

        $v = BestChangeMappingVerifier::fromStorageApp();
        $ton = $v->verifyCode('TON');
        $gram = $v->verifyCode('GRAM');
        $this->assertSame('ABSENT', strtoupper((string) ($ton['status'] ?? '')));
        $this->assertSame('VERIFIED', strtoupper((string) ($gram['status'] ?? '')));
        $this->assertNotSame(
            (string) ($ton['bestchange_id'] ?? ''),
            (string) ($gram['bestchange_id'] ?? '209')
        );
    }

    public function testPayeerCodesRemainAbsent(): void
    {
        $v = BestChangeMappingVerifier::fromStorageApp();
        foreach (['PRUSD', 'PREUR', 'PRRUB'] as $code) {
            $st = strtoupper((string) ($v->verifyCode($code)['status'] ?? ''));
            $this->assertSame('ABSENT', $st, $code);
        }
    }

    public function testCardvndIsNotPrusd(): void
    {
        $v = BestChangeMappingVerifier::fromStorageApp();
        $cardvnd = $v->verifyCode('CARDVND');
        $this->assertSame('VERIFIED', strtoupper((string) ($cardvnd['status'] ?? '')));
        $this->assertSame('108', (string) ($cardvnd['bestchange_currency_id'] ?? ''));
    }
}
