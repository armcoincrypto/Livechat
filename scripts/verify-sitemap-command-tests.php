#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * P7.4B — Runnable sitemap command policy tests (PHPUnit mirror when vendor/bin/phpunit unavailable).
 *
 * Usage (from Laravel app root):
 *   /usr/bin/php8.4 scripts/verify-sitemap-command-tests.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$failures = 0;

function p74b_assert(bool $cond, string $msg): void
{
    global $failures;
    if (!$cond) {
        echo "FAIL: {$msg}\n";
        $failures++;
    } else {
        echo "OK: {$msg}\n";
    }
}

$phpunit = __DIR__ . '/../vendor/bin/phpunit';
if (is_executable($phpunit)) {
    passthru('/usr/bin/php8.4 ' . escapeshellarg($phpunit) . ' --configuration ' . escapeshellarg(__DIR__ . '/../phpunit.xml') . ' tests/Unit/UpdateSitemapCommandTest.php 2>&1', $exitCode);
    exit($exitCode === 0 ? 0 : 1);
}

// Fallback: run equivalent checks inline
$sitemapPath = __DIR__ . '/../public/static/seo/sitemap.xml';
$tierPath = __DIR__ . '/../storage/app/seo/exchange_index_tier_ids.txt';

if (!is_file($sitemapPath)) {
    echo "FAIL: missing sitemap at {$sitemapPath}\n";
    exit(1);
}

$xml = file_get_contents($sitemapPath);
preg_match_all('#<loc>(https?://[^<]+)</loc>#', $xml ?: '', $m);
$locs = $m[1] ?? [];

p74b_assert(count($locs) === count(array_unique($locs)), 'sitemap locs are unique');
p74b_assert((bool) preg_grep('#/ru/blog$#', $locs), '/ru/blog hub in sitemap');
p74b_assert(empty(preg_grep('#/ru/news#', $locs) ?: []), '/ru/news excluded from sitemap');

$guides = [
    'obmen-usdt-na-rubli', 'monitoring-kriptovalyutnyh-obmennikov', 'seti-usdt',
    'bezopasnyj-kriptoobmen', 'obmen-usdt-trc20', 'obmen-usdt-na-kartu',
];
foreach ($guides as $slug) {
    p74b_assert((bool) preg_grep('#/ru/guides/' . preg_quote($slug, '#') . '$#', $locs), "guide {$slug} in sitemap");
}

$badPatterns = [
    '#/pages/contacts$#' => 'pages/contacts',
    '#/pages/AMLKYC#' => 'uppercase AMLKYC',
    '#instrukciia-20#' => 'wrong blog slug instrukciia-20',
];
$badFound = false;
foreach ($locs as $url) {
    foreach ($badPatterns as $pattern => $label) {
        if (preg_match($pattern, $url)) {
            p74b_assert(false, "sitemap contains forbidden {$label}: {$url}");
            $badFound = true;
        }
    }
}
if (!$badFound) {
    p74b_assert(true, 'sitemap excludes redirect-only duplicate paths');
}

$exchangeLocs = array_filter($locs, static fn ($u) => (bool) preg_match('#/ru/exchange/[^/]+/[^/]+$#', $u));
$tierCount = 0;
foreach (file($tierPath) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) {
        continue;
    }
    if ((int) (preg_split('/\s+/', $line)[0] ?? 0) > 0) {
        $tierCount++;
    }
}
p74b_assert(count($exchangeLocs) === $tierCount, 'exchange URL count matches INDEX tier file');

echo $failures === 0
    ? "\nSITEMAP_COMMAND_TESTS_STATUS=PASS\n"
    : "\nSITEMAP_COMMAND_TESTS_STATUS=FAIL ({$failures})\n";

exit($failures === 0 ? 0 : 1);
