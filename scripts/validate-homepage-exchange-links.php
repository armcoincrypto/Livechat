<?php

declare(strict_types=1);

/**
 * P-FOOTER-SEO-3: Validate generated homepage exchange link artifacts.
 *
 * Usage: php scripts/validate-homepage-exchange-links.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$base = dirname(__DIR__);
$htmlDir = $base . '/public/static/seo';
$nginxDir = $base . '/storage/app/seo/generated';

$expectedHeadings = [
    'en' => 'Popular exchange directions',
    'ru' => 'Популярные направления обмена',
    'uk' => 'Популярні напрямки обміну',
    'ka' => 'პოპულარული გაცვლის მიმართულებები',
];

$failures = 0;

function check(bool $cond, string $msg): void
{
    global $failures;
    if (!$cond) {
        echo "FAIL: {$msg}\n";
        $failures++;
    } else {
        echo "OK: {$msg}\n";
    }
}

function fetchStatus(string $url): int
{
    $cmd = 'curl -s -o /dev/null -w %{http_code} ' . escapeshellarg($url);
    $output = trim((string) shell_exec($cmd));

    return is_numeric($output) ? (int) $output : 0;
}

echo "=== File presence ===\n";
foreach (['ru', 'en', 'uk', 'ka'] as $locale) {
    check(
        is_file("{$htmlDir}/homepage-exchange-links.{$locale}.html"),
        "HTML mirror exists: homepage-exchange-links.{$locale}.html"
    );
    check(
        is_file("{$nginxDir}/exswaping-homepage-seo-subfilter-{$locale}.conf"),
        "nginx snippet exists: exswaping-homepage-seo-subfilter-{$locale}.conf"
    );
}
check(is_file("{$htmlDir}/homepage-exchange-links.zh.html"), 'HTML mirror exists: homepage-exchange-links.zh.html');
check(is_file("{$nginxDir}/exswaping-homepage-seo-subfilter-zh.conf"), 'nginx snippet exists: exswaping-homepage-seo-subfilter-zh.conf');

echo "\n=== Per-locale HTML ===\n";
foreach ($expectedHeadings as $locale => $heading) {
    $path = "{$htmlDir}/homepage-exchange-links.{$locale}.html";
    $html = (string) file_get_contents($path);
    check(str_contains($html, '<h2'), "{$locale}: h2 present");
    check(str_contains($html, $heading), "{$locale}: expected heading \"{$heading}\"");
    check(str_contains($html, 'class="seo-footer-links"'), "{$locale}: enterprise footer class present");
    check(str_contains($html, 'seo-footer-links__nav'), "{$locale}: nav grid present");
    check(!str_contains($html, 'seo-dir-link'), "{$locale}: no legacy pill classes");

    preg_match_all('#<a\b[^>]*\bhref="([^"]+)"[^>]*>([^<]+)</a>#', $html, $matches, PREG_SET_ORDER);
    check(count($matches) === 12, "{$locale}: exactly 12 links (found " . count($matches) . ')');

    foreach ($matches as $match) {
        [$full, $href, $label] = $match;
        check(str_starts_with($href, "https://exswaping.com/{$locale}/exchange/"), "{$locale}: href locale prefix OK ({$href})");
        $status = fetchStatus($href);
        check($status === 200, "{$locale}: HTTP 200 {$href} (got {$status})");
    }

    if ($locale === 'en') {
        foreach ($matches as $match) {
            $label = $match[2];
            check(!preg_match('/[\x{0400}-\x{04FF}]/u', $label), "en: no Cyrillic in \"{$label}\"");
            check(stripos($label, 'PPRIVAT') === false, "en: no PPRIVAT in \"{$label}\"");
            check(!preg_match('/\bSBER RUB\b/i', $label), "en: no raw SBER RUB in \"{$label}\"");
            check(!preg_match('/\bTINKOFF\b/', $label), "en: no raw TINKOFF in \"{$label}\"");
        }
    }
}

echo "\n=== ZH omission ===\n";
$zhHtml = (string) file_get_contents("{$htmlDir}/homepage-exchange-links.zh.html");
check(!preg_match('#href="https://exswaping\.com/zh/exchange/#', $zhHtml), 'zh HTML: no /zh/exchange/ hrefs');
check(str_contains($zhHtml, 'zh exchange links omitted'), 'zh HTML: omission comment present');

$zhNginx = (string) file_get_contents("{$nginxDir}/exswaping-homepage-seo-subfilter-zh.conf");
check(!str_contains($zhNginx, "sub_filter '</body>'"), 'zh nginx: no sub_filter injection');
check(str_contains($zhNginx, 'zh exchange links omitted'), 'zh nginx: omission comment present');

$zhLiveStatus = fetchStatus('https://exswaping.com/zh/exchange/USDTTRC20/SBERRUB');
check($zhLiveStatus === 404, "live probe: /zh/exchange/* returns 404 (got {$zhLiveStatus})");

echo "\n=== Summary ===\n";
if ($failures === 0) {
    echo "PASS: validate-homepage-exchange-links\n";
    exit(0);
}

echo "FAIL: {$failures} check(s) failed\n";
exit(1);
