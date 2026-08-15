<?php

declare(strict_types=1);

namespace Tests\Unit\Payments;

use iEXPackages\Payments\Callback\Security\CallbackUrlHashGuard;
use Tests\TestCase;

final class CallbackUrlHashGuardTest extends TestCase
{
    public function test_missing_and_empty_signature_rejected_when_required(): void
    {
        $missing = CallbackUrlHashGuard::verify('secret-hash', '', true);
        $this->assertFalse($missing['ok']);
        $this->assertSame('signature_missing', $missing['event']);
        $this->assertSame(403, $missing['http']);

        $emptyExpected = CallbackUrlHashGuard::verify('', '', true);
        $this->assertFalse($emptyExpected['ok']);
        $this->assertSame('signature_missing', $emptyExpected['event']);

        $emptyProvided = CallbackUrlHashGuard::verify('secret-hash', '   ', true);
        $this->assertFalse($emptyProvided['ok']);
        $this->assertSame('signature_missing', $emptyProvided['event']);
    }

    public function test_invalid_signature_rejected(): void
    {
        $r = CallbackUrlHashGuard::verify('secret-hash', 'other-hash', true);
        $this->assertFalse($r['ok']);
        $this->assertSame('signature_invalid', $r['event']);
        $this->assertSame(403, $r['http']);
    }

    public function test_valid_signature_accepted(): void
    {
        $r = CallbackUrlHashGuard::verify('secret-hash', 'secret-hash', true);
        $this->assertTrue($r['ok']);
    }

    public function test_unsigned_protocol_may_skip_url_hash(): void
    {
        $this->assertFalse(CallbackUrlHashGuard::urlHashRequired(['enabled' => true, 'url_hash_required' => false]));
        $r = CallbackUrlHashGuard::verify('', '', false);
        $this->assertTrue($r['ok']);
    }

    public function test_enabled_callback_defaults_to_required_hash(): void
    {
        $this->assertTrue(CallbackUrlHashGuard::urlHashRequired(['enabled' => true]));
        $this->assertFalse(CallbackUrlHashGuard::urlHashRequired(['enabled' => false]));
    }
}
