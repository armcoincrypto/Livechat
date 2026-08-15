<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Page;

$slug = $argv[1] ?? 'about';
$locale = $argv[2] ?? 'ru';

$page = Page::query()->where('page_slug', $slug)->first();
if (!$page) {
    fwrite(STDERR, "Page not found: {$slug}\n");
    exit(1);
}

$content = $page->getTranslation('page_content', $locale, false) ?: '';
$title = $page->getTranslation('page_title', $locale, false) ?: '';

preg_match_all('/<h1[^>]*>(.*?)<\/h1>/is', $content, $h1s);
preg_match_all('/<h2[^>]*>(.*?)<\/h2>/is', $content, $h2s);

echo "slug={$slug} locale={$locale} title={$title}\n";
echo 'content_len=' . strlen($content) . "\n";
echo 'h1_count=' . count($h1s[0]) . "\n";
foreach ($h1s[1] as $i => $h) {
    echo '  H1[' . $i . ']: ' . trim(strip_tags($h)) . "\n";
}
echo 'h2_count=' . count($h2s[0]) . "\n";
foreach (array_slice($h2s[1], 0, 12) as $i => $h) {
    echo '  H2[' . $i . ']: ' . trim(strip_tags($h)) . "\n";
}

if (($argv[3] ?? '') === '--dump') {
    file_put_contents("/tmp/page-{$slug}-{$locale}.html", $content);
    echo "dumped to /tmp/page-{$slug}-{$locale}.html\n";
}
