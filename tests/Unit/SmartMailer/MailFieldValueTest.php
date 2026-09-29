<?php

declare(strict_types=1);

namespace Tests\Unit\SmartMailer;

use iEXPackages\SmartMailer\Support\MailFieldValue;
use PHPUnit\Framework\TestCase;

final class MailFieldValueTest extends TestCase
{
    public function test_string_passthrough(): void
    {
        $this->assertSame('abc', MailFieldValue::display('abc'));
    }

    public function test_null_uses_fallback(): void
    {
        $this->assertSame('n/a', MailFieldValue::display(null, 'n/a'));
    }

    public function test_array_does_not_throw_for_htmlspecialchars(): void
    {
        $out = MailFieldValue::display(['a', 'b'], 'n/a');
        $this->assertSame('a, b', $out);
        // Must be a string htmlspecialchars can accept.
        $this->assertIsString(htmlspecialchars($out, ENT_QUOTES, 'UTF-8'));
    }

    public function test_nested_array_json_fragment(): void
    {
        $out = MailFieldValue::display(['x' => ['y' => 1]], 'n/a');
        $this->assertNotSame('', $out);
        $this->assertIsString(htmlspecialchars($out, ENT_QUOTES, 'UTF-8'));
    }
}
