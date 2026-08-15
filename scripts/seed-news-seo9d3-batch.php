#!/usr/bin/env php
<?php
/**
 * SEO-9D.3 — Content quality recovery (batches A–E).
 */
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\News;

$configPath = __DIR__ . '/../storage/app/seo/news-seo9d3-config.json';
$contentDir = __DIR__ . '/../storage/app/seo/content';
$snapshotDir = __DIR__ . '/../storage/app/seo/news-ssr-snapshots';
$snapshotDirPublic = '/var/www/exswaping_co_usr/data/www/exswaping.com/storage/seo/news-ssr-snapshots';

$config = json_decode((string) file_get_contents($configPath), true);
$trustRu = (string) ($config['trust_strip_ru'] ?? '');
$trustEn = (string) ($config['trust_strip_en'] ?? '');

function extractBlock(string $html, string $classPrefix): string
{
    if (preg_match('/<div class="' . preg_quote($classPrefix, '/') . '[^"]*"[^>]*>.*?<\/div>/si', $html, $m)) {
        return $m[0];
    }
    return '';
}

function stripSeoWrappers(string $html): string
{
    foreach (['news-seo9d1-intro', 'news-seo9d3-intro', 'news-seo9d1-cta', 'news-seo9d3-cta', 'news-seo9d3-trust'] as $pfx) {
        $html = preg_replace('/<div class="' . preg_quote($pfx, '/') . '[^"]*"[^>]*>.*?<\/div>/si', '', $html) ?? $html;
    }
    return trim($html);
}

function demoteH1(string $html): string
{
    return preg_replace(['/<h1\b/i', '/<\/h1>/i'], ['<h2', '</h2>'], $html) ?? $html;
}

function appendIfMissing(string $html, string $block, string $needle): string
{
    return ($block !== '' && !str_contains($html, $needle)) ? $html . $block : $html;
}

$updated = 0;
foreach ($config['articles'] as $article) {
    $id = (int) ($article['id'] ?? 0);
    $news = News::query()->find($id);
    if (!$news) {
        fwrite(STDERR, "Missing news id={$id}\n");
        exit(1);
    }

    $textRu = (string) $news->getTranslation('text', 'ru');
    $existingIntro = extractBlock($textRu, 'news-seo9d1-intro') ?: extractBlock($textRu, 'news-seo9d3-intro');
    $existingOutro = extractBlock($textRu, 'news-seo9d1-cta') ?: extractBlock($textRu, 'news-seo9d3-cta');
    $intro = (string) ($article['intro_html'] ?? '') ?: $existingIntro;
    $outro = (string) ($article['outro_html'] ?? '') ?: $existingOutro;

    if (!empty($article['replace_legacy_body'])) {
        $bodyPath = $contentDir . '/' . ($article['body_ru_file'] ?? '');
        if (!is_readable($bodyPath)) {
            fwrite(STDERR, "Missing body: {$bodyPath}\n");
            exit(1);
        }
        $core = file_get_contents($bodyPath);
    } else {
        $core = stripSeoWrappers($textRu);
        if (!empty($article['demote_h1'])) {
            $core = demoteH1($core);
        }
    }

    $textRu = $intro . $core . $outro;
    if (!empty($article['add_trust_strip'])) {
        $textRu = appendIfMissing($textRu, $trustRu, 'news-seo9d3-trust');
    }

    $titleRu = (string) ($article['title_ru'] ?? '');
    if ($titleRu !== '') {
        $news->setTranslation('name', 'ru', $titleRu);
    }
    $news->setTranslation('text', 'ru', $textRu);

    $titleEn = (string) ($article['title_en'] ?? '');
    if ($titleEn !== '') {
        $news->setTranslation('name', 'en', $titleEn);
    }

    if (!empty($article['body_en_file'])) {
        $enPath = $contentDir . '/' . $article['body_en_file'];
        $textEn = file_get_contents($enPath);
        $textEn = appendIfMissing($textEn, $trustEn, 'news-seo9d3-trust');
        $news->setTranslation('text', 'en', $textEn);
    } elseif (!empty($article['regenerate_en_snapshot']) || !empty($article['add_trust_strip'])) {
        $textEn = appendIfMissing(stripSeoWrappers((string) $news->getTranslation('text', 'en')), $trustEn, 'news-seo9d3-trust');
        $news->setTranslation('text', 'en', $textEn);
    }

    $news->save();

    $h1Ru = $titleRu !== '' ? $titleRu : (string) $news->getTranslation('name', 'ru');
    $snapRu = '<div class="news-seo9d3-ssr"><h1 class="text-2xl font-semibold mb-4">' . htmlspecialchars($h1Ru, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h1>' . $textRu . '</div>';
    file_put_contents("{$snapshotDir}/{$id}.ru.html", $snapRu);
    file_put_contents("{$snapshotDirPublic}/{$id}.ru.html", $snapRu);

    $enBody = (string) $news->getTranslation('text', 'en');
    if ($enBody !== '') {
        $h1En = $titleEn !== '' ? $titleEn : (string) $news->getTranslation('name', 'en');
        $snapEn = '<div class="news-seo9d3-ssr"><h1 class="text-2xl font-semibold mb-4">' . htmlspecialchars($h1En, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h1>' . $enBody . '</div>';
        file_put_contents("{$snapshotDir}/{$id}.en.html", $snapEn);
        file_put_contents("{$snapshotDirPublic}/{$id}.en.html", $snapEn);
    }

    echo "OK news_id={$id}\n";
    $updated++;
}
echo "SUMMARY updated={$updated}\n";
