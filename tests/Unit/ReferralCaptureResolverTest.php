<?php

declare(strict_types=1);

namespace Tests\Unit;

use iEXPackages\ReferralSystem\ReferralCookieManager;
use iEXPackages\ReferralSystem\Services\ReferralCaptureResolver;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * Unit coverage for referral attribution resolution.
 * DB-backed link id (e.g. MLyn→436) is verified by
 * scripts/verify-referral-capture-resolver.php in ops, not hard-required here.
 */
final class ReferralCaptureResolverTest extends TestCase
{
    private ReferralCaptureResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $cookies = app(ReferralCookieManager::class);
        $this->resolver = new ReferralCaptureResolver($cookies);
    }

    public function test_extract_from_referer_parses_ref(): void
    {
        $token = $this->resolver->extractFromReferer(
            'https://exswaping.com/ru/?ref=MLyn&cur_from=USDT'
        );
        $this->assertSame('MLyn', $token);
    }

    public function test_body_ref_resolves_code_and_source(): void
    {
        $req = Request::create('/client-api/v1/order', 'POST', ['ref' => 'MLyn']);
        $result = $this->resolver->resolve($req, null);

        $this->assertSame('MLyn', $result->code);
        $this->assertSame('body', $result->source);
        // linkId is DB-dependent; when present it must be positive.
        if ($result->linkId !== 0) {
            $this->assertGreaterThan(0, $result->linkId);
        }
    }

    public function test_cookie_ref_resolves_code_and_source(): void
    {
        $req = Request::create('/client-api/v1/order', 'POST');
        $req->cookies->set('ref', 'MLyn');
        $result = $this->resolver->resolve($req, null);

        $this->assertSame('MLyn', $result->code);
        $this->assertSame('cookie', $result->source);
    }

    public function test_no_referral_returns_empty(): void
    {
        $req = Request::create('/client-api/v1/order', 'POST');
        $result = $this->resolver->resolve($req, null);

        $this->assertFalse($result->hasCode());
    }
}
