<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Services\Mail\OrderMailDisplayText;
use PHPUnit\Framework\TestCase;

final class OrderMailDisplayTextTest extends TestCase
{
    public function test_empty_translatable_array_is_safe_for_htmlspecialchars(): void
    {
        $scalar = OrderMailDisplayText::scalar([]);
        $this->assertSame('', $scalar);
        $escaped = htmlspecialchars($scalar, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $this->assertSame('', $escaped);
    }

    public function test_order_5150_field_name_from_shape_uses_fallback(): void
    {
        $this->assertSame(
            'Wallet',
            OrderMailDisplayText::scalar([], 'Wallet'),
        );
    }

    public function test_locale_map_prefers_requested_locale(): void
    {
        $this->assertSame(
            'Stellar',
            OrderMailDisplayText::scalar(
                ['ru' => 'Стеллар', 'en' => 'Stellar'],
                '',
                ['en', 'ru'],
            ),
        );
        $this->assertSame(
            'Стеллар',
            OrderMailDisplayText::scalar(
                ['ru' => 'Стеллар', 'en' => 'Stellar'],
                '',
                ['ru', 'en'],
            ),
        );
    }

    public function test_null_and_missing_optional_values_use_fallback(): void
    {
        $this->assertSame('', OrderMailDisplayText::scalar(null));
        $this->assertSame('n/a', OrderMailDisplayText::scalar(null, 'n/a'));
        $this->assertSame('', OrderMailDisplayText::scalar(new \stdClass(), ''));
    }

    public function test_scalar_path_is_unchanged(): void
    {
        $this->assertSame('XLM', OrderMailDisplayText::scalar('XLM'));
        $this->assertSame('12.5', OrderMailDisplayText::scalar(12.5));
    }

    public function test_html_escaping_still_applies_to_normalized_strings(): void
    {
        $raw = OrderMailDisplayText::scalar('<b>ALFA</b>');
        $this->assertSame('&lt;b&gt;ALFA&lt;/b&gt;', htmlspecialchars($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }

    public function test_does_not_cast_arrays_with_php_string_cast(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/Mail/OrderMailDisplayText.php');
        $this->assertStringContainsString('if (! is_array($value))', $src);
        $this->assertStringContainsString('if ($value === [])', $src);
        $this->assertDoesNotMatchRegularExpression(
            '/if \(is_array\(\$value\)\)[\s\S]{0,200}\(string\) \$value/',
            $src,
        );
    }
}
