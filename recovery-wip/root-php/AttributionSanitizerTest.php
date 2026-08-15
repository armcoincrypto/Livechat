<?php

declare(strict_types=1);

namespace Tests\Unit\Analytics;

use App\Services\Analytics\AttributionSanitizer;
use PHPUnit\Framework\TestCase;

final class AttributionSanitizerTest extends TestCase
{
    private AttributionSanitizer $s;

    protected function setUp(): void
    {
        $this->s = new AttributionSanitizer();
    }

    public function test_rejects_email_and_wallet_like_utm(): void
    {
        $this->assertNull($this->s->sanitizeUtm('user@exswaping.com', 64));
        $this->assertNull($this->s->sanitizeUtm('0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 64));
    }

    public function test_accepts_normal_utm_and_caps_length(): void
    {
        $this->assertSame('google', $this->s->sanitizeUtm(' google ', 64));
        $long = str_repeat('a', 200);
        $this->assertSame(64, strlen((string) $this->s->sanitizeUtm($long, 64)));
    }

    public function test_referrer_strips_internal_and_query(): void
    {
        $this->assertNull($this->s->sanitizeReferrer('https://exswaping.com/ru/?utm_source=x'));
        $this->assertSame(
            'https://example.com/path',
            $this->s->sanitizeReferrer('https://example.com/path?email=a@b.com#frag')
        );
    }

    public function test_locale_and_session_id(): void
    {
        $this->assertSame('ru', $this->s->sanitizeLocale('RU'));
        $this->assertNull($this->s->sanitizeLocale('xx'));
        $this->assertNotNull($this->s->sanitizeSessionId('abcdEFGH12345678_xyz'));
        $this->assertNull($this->s->sanitizeSessionId('short'));
        $this->assertNull($this->s->sanitizeSessionId('bad session id!!!'));
    }

    public function test_payload_never_throws_on_garbage(): void
    {
        $out = $this->s->sanitizePayload(['public_session_id' => ['x'], 'utm_source' => new \stdClass()]);
        $this->assertNull($out['public_session_id']);
    }
}
