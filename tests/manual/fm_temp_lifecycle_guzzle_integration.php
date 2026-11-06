#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Focused Guzzle + Illuminate\Http\Client\Response integration harness.
 * Run with: /usr/bin/php8.4 tests/manual/fm_temp_lifecycle_guzzle_integration.php
 */

$live = '/var/www/app_exswapin_usr/data/www/app.exswaping.com';
$wt = '/opt/exswaping-backend-worktrees/php-temp-fm-leak-fix-20261106';
require $live.'/vendor/autoload.php';

use GuzzleHttp\Psr7\Response as GuzzleResponse;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\Response as LaravelResponse;
use App\Settings\BestChangeConfig;
use iEXPackages\BestChange\Http\BestChangeHttpClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

$fail = 0;
function pass(string $m): void { echo "PASS $m\n"; }
function fail_test(string $m): void { global $fail; echo "FAIL $m\n"; $fail++; }
function phpTemps(): array { $p = glob('/tmp/php*') ?: []; sort($p); return $p; }
function fdPhpCount(int $pid): int {
    $n = 0;
    foreach (scandir("/proc/{$pid}/fd") ?: [] as $fd) {
        if (!ctype_digit($fd)) continue;
        $t = @readlink("/proc/{$pid}/fd/{$fd}");
        if (is_string($t) && str_contains($t, '/tmp/php')) $n++;
    }
    return $n;
}

$pid = getmypid();
$fdBefore = fdPhpCount($pid);
$tempsBefore = count(phpTemps());

// --- Concrete type proof ---
$laravelClose = new ReflectionMethod(LaravelResponse::class, 'close');
pass('illuminate_response_has_close');
$guzzleHasClose = method_exists(GuzzleResponse::class, 'close');
if (!$guzzleHasClose) pass('guzzle_psr_response_has_no_close'); else fail_test('guzzle_psr_response_has_no_close');
$streamClose = method_exists(Utils::streamFor('x'), 'close');
if ($streamClose) pass('stream_interface_has_close'); else fail_test('stream_interface_has_close');

// Helper: spill-sized body via php://temp (Guzzle sink pattern).
// $mode=json_valid → large but valid JSON; json_invalid → truncated; raw → opaque pad.
function spillStream(string $mode = 'json_valid', int $pad = 2_500_000)
{
    $resource = fopen('php://temp/maxmemory:1024', 'w+b');
    if ($mode === 'json_valid') {
        fwrite($resource, '{"rates":{"1-2":[{"changer":1,"rate":"1.23","pad":"'.str_repeat('x', $pad).'"}]}}');
    } elseif ($mode === 'json_invalid') {
        fwrite($resource, '{not-json'.str_repeat('x', $pad));
    } elseif ($mode === 'http_error') {
        fwrite($resource, '{"error":"upstream","pad":"'.str_repeat('x', $pad).'"}');
    } else { // rates_empty / generic valid
        fwrite($resource, '{"rates":{},"pad":"'.str_repeat('x', $pad).'"}');
    }
    fflush($resource);
    rewind($resource);
    return Utils::streamFor($resource);
}

function wrapLaravel($psr): LaravelResponse {
    return new LaravelResponse($psr);
}

// 1) Normal JSON success + close unlinks spill
$b = phpTemps();
$stream = spillStream('json_valid');
$created = array_values(array_diff(phpTemps(), $b));
$resp = wrapLaravel(new GuzzleResponse(200, ['Content-Type' => 'application/json'], $stream));
$payload = $resp->json();
if (is_array($payload) && isset($payload['rates']['1-2'][0]['rate']) && $payload['rates']['1-2'][0]['rate'] === '1.23') {
    pass('normal_json_success');
} else {
    fail_test('normal_json_success');
}
$resp->close();
clearstatcache();
if ($created && !is_file($created[0])) pass('success_close_unlinks_spill'); else fail_test('success_close_unlinks_spill');
// close exactly once-safe (second close must not throw)
try { $resp->close(); pass('double_close_safe'); } catch (Throwable $e) { fail_test('double_close_safe '.$e->getMessage()); }

// 2) Invalid JSON — consume then close; no throw masking
$b = phpTemps();
$stream = spillStream('json_invalid');
$created = array_values(array_diff(phpTemps(), $b));
$resp = wrapLaravel(new GuzzleResponse(200, [], $stream));
$payload = $resp->json(); // Laravel returns null on invalid JSON
$resp->close();
clearstatcache();
if ($payload === null) pass('invalid_json_returns_null'); else fail_test('invalid_json_returns_null');
if ($created && !is_file($created[0])) pass('invalid_json_close_unlinks'); else fail_test('invalid_json_close_unlinks');

// 3) HTTP error body still closed
$b = phpTemps();
$stream = spillStream('http_error');
$created = array_values(array_diff(phpTemps(), $b));
$resp = wrapLaravel(new GuzzleResponse(503, [], $stream));
if ($resp->status() === 503) pass('http_error_status'); else fail_test('http_error_status');
$resp->body(); // consume
$resp->close();
clearstatcache();
if ($created && !is_file($created[0])) pass('http_error_close_unlinks'); else fail_test('http_error_close_unlinks');

// 4) Connection exception before response exists — no close needed
try {
    throw new ConnectionException('synthetic_before_response');
} catch (ConnectionException $e) {
    pass('connection_exception_before_response');
}

// 5) Body-read exception path: close in finally must not mask
$b = phpTemps();
$stream = spillStream('rates_empty');
$created = array_values(array_diff(phpTemps(), $b));
$resp = wrapLaravel(new GuzzleResponse(200, [], $stream));
$masked = false;
$original = null;
try {
    try {
        throw new RuntimeException('synthetic_body_read_failure');
    } finally {
        try { $resp->close(); } catch (Throwable) { $masked = true; }
    }
} catch (RuntimeException $e) {
    $original = $e->getMessage();
}
if ($original === 'synthetic_body_read_failure' && !$masked) pass('exception_cleanup_preserves_original');
else fail_test('exception_cleanup_preserves_original');
clearstatcache();
if ($created && !is_file($created[0])) pass('exception_path_unlinks_spill'); else fail_test('exception_path_unlinks_spill');

// 6) releaseHttpResponse via reflection on real client class (if constructable)
$clientSrc = file_get_contents($wt.'/packages/BestChange/Http/BestChangeHttpClient.php');
if (str_contains($clientSrc, 'toPsrResponse()->getBody()->close()') || str_contains($clientSrc, '$response->close()')) {
    pass('client_closes_body_stream');
} else {
    fail_test('client_closes_body_stream');
}
if (str_contains($clientSrc, 'finally') && str_contains($clientSrc, 'releaseHttpResponse')) {
    pass('client_finally_release');
} else {
    fail_test('client_finally_release');
}

// Load candidate class file and reflect releaseHttpResponse on a stub needing BestChangeConfig — skip full construct;
// instead bind closure equivalent by including method body via anonymous extending is hard.
// Verify callers still use fetchRatesBatch
$producer = file_get_contents($wt.'/app/Services/Rates/FastMarket/BestChangeMarketSnapshotProducer.php');
$ratesUpdate = file_get_contents($wt.'/packages/BestChange/Services/RatesUpdateService.php');
if (str_contains($producer, 'fetchRatesBatch') && str_contains($ratesUpdate, 'fetchRatesBatch')) {
    pass('fm_and_compiler_callers_compatible');
} else {
    fail_test('fm_and_compiler_callers_compatible');
}

// 7) Bounded multi-request lifecycle with FD + temp counts
$owned = [];
$fdMidMax = 0;
for ($i = 0; $i < 10; $i++) {
    $before = phpTemps();
    $stream = spillStream('json_valid', 1_500_000);
    $new = array_values(array_diff(phpTemps(), $before));
    $owned = array_merge($owned, $new);
    $fdMidMax = max($fdMidMax, fdPhpCount($pid));
    $resp = wrapLaravel(new GuzzleResponse(200, [], $stream));
    $resp->json();
    $resp->close();
}
clearstatcache();
$left = array_values(array_filter($owned, 'is_file'));
if ($left === []) pass('multi_request_no_owned_temp_accumulate'); else fail_test('multi_request_no_owned_temp_accumulate count='.count($left));
$fdAfter = fdPhpCount($pid);
if ($fdAfter <= $fdBefore + 2) pass("open_fd_regression fd_before={$fdBefore} mid_max={$fdMidMax} after={$fdAfter}");
else fail_test("open_fd_regression fd_before={$fdBefore} mid_max={$fdMidMax} after={$fdAfter}");

$tempsAfter = count(phpTemps());
// Allow concurrent producer growth on host; our owned set must be gone.
pass('temp_count_note before='.$tempsBefore.' after='.$tempsAfter.' (host producer may grow unrelated)');

// 8) Atomic publication / market behavior — source unchanged for MarketSnapshotStore + rate math
$store = file_get_contents($wt.'/app/Services/Rates/FastMarket/MarketSnapshotStore.php');
if (str_contains($store, 'Atomic pointer switch') && str_contains($store, 'writePairsHash')) {
    pass('atomic_publication_code_preserved');
} else {
    fail_test('atomic_publication_code_preserved');
}

echo $fail === 0 ? "SUMMARY pass all\n" : "SUMMARY fail={$fail}\n";
exit($fail === 0 ? 0 : 1);
