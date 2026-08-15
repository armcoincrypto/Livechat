<?php

declare(strict_types=1);

use iEXPackages\ReferralSystem\ReferralCookieManager;
use iEXPackages\ReferralSystem\Services\ReferralCaptureResolver;
use Illuminate\Http\Request;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cookies = $app->make(ReferralCookieManager::class);
$resolver = new ReferralCaptureResolver($cookies);

$failures = 0;

function assertTrue(bool $cond, string $msg): void
{
    global $failures;
    if (!$cond) {
        echo "FAIL: {$msg}\n";
        $failures++;
    } else {
        echo "OK: {$msg}\n";
    }
}

// 1) Referer fallback extracts MLyn
$referer = 'https://exswaping.com/ru/?ref=MLyn&cur_from=USDT';
$token = $resolver->extractFromReferer($referer);
assertTrue($token === 'MLyn', 'extractFromReferer parses ref=MLyn');

// 2) Query fallback
$req = Request::create('/client-api/v1/order', 'POST', ['ref' => 'MLyn']);
$result = $resolver->resolve($req, null);
assertTrue($result->code === 'MLyn', 'body/query ref=MLyn resolves to MLyn code');
assertTrue($result->linkId === 436, 'MLyn link id is 436');
assertTrue($result->source === 'body', 'POST ref source is body');

// 3) Cookie fallback
$req = Request::create('/client-api/v1/order', 'POST', [], [], [], [], null);
$req->cookies->set('ref', 'MLyn');
$result = $resolver->resolve($req, null);
assertTrue($result->code === 'MLyn', 'cookie ref=MLyn resolves');
assertTrue($result->source === 'cookie', 'cookie source');

// 4) No referral => empty
$req = Request::create('/client-api/v1/order', 'POST');
$result = $resolver->resolve($req, null);
assertTrue(!$result->hasCode(), 'no referral returns empty');

echo $failures === 0
    ? "\nAll referral capture resolver checks passed.\n"
    : "\n{$failures} check(s) failed.\n";

exit($failures === 0 ? 0 : 1);
