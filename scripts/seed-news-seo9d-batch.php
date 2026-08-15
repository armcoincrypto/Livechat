#!/usr/bin/env php
<?php
/**
 * SEO-9D — Optimize first batch of RU news/blog articles (content + snapshots).
 * Idempotent: skips intro/outro blocks if marker already present.
 */
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\News;

$configPath = __DIR__ . '/../storage/app/seo/news-seo9d-config.json';
$snapshotDir = __DIR__ . '/../storage/app/seo/news-ssr-snapshots';
$snapshotDirPublic = '/var/www/exswaping_co_usr/data/www/exswaping.com/storage/seo/news-ssr-snapshots';
$marker = 'news-seo9d-intro';

if (!is_readable($configPath)) {
    fwrite(STDERR, "Missing config: {$configPath}\n");
    exit(1);
}

$config = json_decode((string) file_get_contents($configPath), true);
if (!is_array($config['articles'] ?? null)) {
    fwrite(STDERR, "Invalid config JSON\n");
    exit(1);
}

if (!is_dir($snapshotDir) && !mkdir($snapshotDir, 0755, true) && !is_dir($snapshotDir)) {
    fwrite(STDERR, "Cannot create snapshot dir: {$snapshotDir}\n");
    exit(1);
}
if (!is_dir($snapshotDirPublic) && !mkdir($snapshotDirPublic, 0755, true) && !is_dir($snapshotDirPublic)) {
    fwrite(STDERR, "Cannot create public snapshot dir: {$snapshotDirPublic}\n");
    exit(1);
}

$updated = 0;

foreach ($config['articles'] as $article) {
    $id = (int) ($article['id'] ?? 0);
    if ($id <= 0) {
        continue;
    }

    $news = News::query()->find($id);
    if (!$news) {
        fwrite(STDERR, "Missing news id={$id}\n");
        exit(1);
    }

    $intro = (string) ($article['intro_html'] ?? '');
    $outro = (string) ($article['outro_html'] ?? '');
    $titleRu = (string) ($article['title_ru'] ?? '');

    $text = (string) $news->getTranslation('text', 'ru');

    if ($intro !== '' && !str_contains($text, $marker)) {
        $text = $intro . $text;
    }
    if ($outro !== '' && !str_contains($text, 'news-seo9d-cta')) {
        $text .= $outro;
    }

    if ($titleRu !== '') {
        $news->setTranslation('name', 'ru', $titleRu);
    }

    $news->setTranslation('text', 'ru', $text);
    $news->save();

    $h1 = $titleRu !== '' ? $titleRu : (string) $news->getTranslation('name', 'ru');
    $snapshot = '<div class="news-seo9d-ssr"><h1 class="text-2xl font-semibold mb-4">' . htmlspecialchars($h1, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h1>' . $text . '</div>';
    $snapshotPath = $snapshotDir . '/' . $id . '.ru.html';
    file_put_contents($snapshotPath, $snapshot);
    file_put_contents($snapshotDirPublic . '/' . $id . '.ru.html', $snapshot);

    echo "OK news_id={$id} slug_suffix=" . ($article['public_slug_suffix'] ?? '') . ' bytes=' . strlen($text) . "\n";
    $updated++;
}

echo "SUMMARY updated={$updated}\n";
