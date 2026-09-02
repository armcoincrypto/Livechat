<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\BestChangeBulkStatusGuard;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * Fail-closed bulk BestChange status semantics (no production DB writes).
 */
final class BestChangeBulkStatusGuardTest extends TestCase
{
    public function test_normalize_ids_drops_invalid_and_dedupes(): void
    {
        $this->assertSame([], BestChangeBulkStatusGuard::normalizeIds(null));
        $this->assertSame([], BestChangeBulkStatusGuard::normalizeIds('1,2'));
        $this->assertSame([], BestChangeBulkStatusGuard::normalizeIds([]));
        $this->assertSame([1, 2], BestChangeBulkStatusGuard::normalizeIds([1, '2', 'x', 0, -3, 1, '02']));
    }

    public function test_empty_ids_deny_zero_writes(): void
    {
        $r = BestChangeBulkStatusGuard::evaluate($this->req(['confirm' => 1]), [], 0);
        $this->assertFalse($r['ok']);
        $this->assertSame(BestChangeBulkStatusGuard::CODE_EMPTY_IDS, $r['code']);
        $this->assertSame(422, $r['http']);
        $this->assertSame(0, $r['payload']['count']);
    }

    public function test_missing_confirm_denies_without_write(): void
    {
        $ids = range(1, 10);
        $r = BestChangeBulkStatusGuard::evaluate($this->req([]), $ids, 0, $ids, 2000);
        $this->assertFalse($r['ok']);
        $this->assertSame(BestChangeBulkStatusGuard::CODE_CONFIRM_REQUIRED, $r['code']);
        $this->assertTrue($r['payload']['requires_confirm'] ?? false);
        $this->assertStringContainsString('Будет выключить', $r['payload']['scope_label'] ?? '');
    }

    public function test_small_batch_with_confirm_ok(): void
    {
        $ids = range(1, 10);
        $r = BestChangeBulkStatusGuard::evaluate(
            $this->req(['confirm' => 1]),
            $ids,
            0,
            $ids,
            2000
        );
        $this->assertTrue($r['ok']);
        $this->assertSame(10, $r['payload']['count']);
    }

    public function test_medium_batch_requires_matching_expected_count(): void
    {
        $ids = range(1, 25);
        $missing = BestChangeBulkStatusGuard::evaluate(
            $this->req(['confirm' => 1]),
            $ids,
            0,
            $ids,
            2000
        );
        $this->assertFalse($missing['ok']);
        $this->assertSame(BestChangeBulkStatusGuard::CODE_EXPECTED_COUNT_REQUIRED, $missing['code']);

        $mismatch = BestChangeBulkStatusGuard::evaluate(
            $this->req(['confirm' => 1, 'expected_count' => 24]),
            $ids,
            0,
            $ids,
            2000
        );
        $this->assertFalse($mismatch['ok']);
        $this->assertSame(BestChangeBulkStatusGuard::CODE_COUNT_MISMATCH, $mismatch['code']);

        $ok = BestChangeBulkStatusGuard::evaluate(
            $this->req(['confirm' => 1, 'expected_count' => 25]),
            $ids,
            0,
            $ids,
            2000
        );
        $this->assertTrue($ok['ok']);
    }

    public function test_large_batch_requires_typed_confirmation(): void
    {
        $ids = range(1, 120);
        $phrase = BestChangeBulkStatusGuard::typedDisablePhrase(120);
        $needTyped = BestChangeBulkStatusGuard::evaluate(
            $this->req(['confirm' => 1, 'expected_count' => 120]),
            $ids,
            0,
            $ids,
            2000
        );
        $this->assertFalse($needTyped['ok']);
        $this->assertSame(BestChangeBulkStatusGuard::CODE_TYPED_CONFIRM_REQUIRED, $needTyped['code']);
        $this->assertSame($phrase, $needTyped['payload']['typed_confirmation_phrase']);

        // 120/2000 = 6% — under 10% circuit breaker once typed confirm present.
        $ok = BestChangeBulkStatusGuard::evaluate(
            $this->req([
                'confirm' => 1,
                'expected_count' => 120,
                'typed_confirmation' => $phrase,
            ]),
            $ids,
            0,
            $ids,
            2000
        );
        $this->assertTrue($ok['ok']);
    }

    public function test_circuit_breaker_blocks_mass_disable_without_emergency(): void
    {
        $ids = range(1, 250);
        $phrase = BestChangeBulkStatusGuard::typedDisablePhrase(250);
        $blocked = BestChangeBulkStatusGuard::evaluate(
            $this->req([
                'confirm' => 1,
                'expected_count' => 250,
                'typed_confirmation' => $phrase,
            ]),
            $ids,
            0,
            $ids,
            2000
        );
        $this->assertFalse($blocked['ok']);
        $this->assertSame(BestChangeBulkStatusGuard::CODE_CIRCUIT_BREAKER, $blocked['code']);

        $ok = BestChangeBulkStatusGuard::evaluate(
            $this->req([
                'confirm' => 1,
                'expected_count' => 250,
                'typed_confirmation' => $phrase,
                'emergency_override' => 1,
            ]),
            $ids,
            0,
            $ids,
            2000
        );
        $this->assertTrue($ok['ok']);
    }

    public function test_phantom_ids_fail_closed(): void
    {
        $ids = [1, 2, 999];
        $r = BestChangeBulkStatusGuard::evaluate(
            $this->req(['confirm' => 1]),
            $ids,
            0,
            [1, 2],
            2000
        );
        $this->assertFalse($r['ok']);
        $this->assertSame(BestChangeBulkStatusGuard::CODE_COUNT_MISMATCH, $r['code']);
    }

    public function test_enable_large_batch_uses_enable_phrase(): void
    {
        $ids = range(1, 101);
        $phrase = BestChangeBulkStatusGuard::typedEnablePhrase(101);
        $r = BestChangeBulkStatusGuard::evaluate(
            $this->req([
                'confirm' => 1,
                'expected_count' => 101,
                'typed_confirmation' => $phrase,
            ]),
            $ids,
            1,
            $ids,
            50
        );
        $this->assertTrue($r['ok']);
    }

    /**
     * @param  array<string,mixed>  $input
     */
    private function req(array $input): Request
    {
        return Request::create('/iexadmin/frontend-api/crypto/bestchange/status/bulk', 'POST', $input);
    }
}
