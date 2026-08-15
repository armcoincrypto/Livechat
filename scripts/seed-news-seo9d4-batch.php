#!/usr/bin/env php
<?php
/**
 * SEO-9D.4 — Full blog portfolio polish (CMS + snapshots).
 */
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\News;

$configPath = __DIR__ . '/../storage/app/seo/news-seo9d4-config.json';
$snapshotDir = __DIR__ . '/../storage/app/seo/news-ssr-snapshots';
$snapshotDirPublic = '/var/www/exswaping_co_usr/data/www/exswaping.com/storage/seo/news-ssr-snapshots';

$config = json_decode((string) file_get_contents($configPath), true);
$trustRu = (string) ($config['trust_strip_ru'] ?? '');
$trustEn = (string) ($config['trust_strip_en'] ?? '');
$guideRub = (string) ($config['guide_strip_ru_usdt_rub'] ?? '');
$guideTrc20 = (string) ($config['guide_strip_ru_usdt_trc20'] ?? '');

function stripDataUriImages(string $html): string
{
    return preg_replace('/<img[^>]*src="data:image[^"]+"[^>]*>/i', '', $html) ?? $html;
}

function demoteH1(string $html): string
{
    return preg_replace(['/<h1\b/i', '/<\/h1>/i'], ['<h2', '</h2>'], $html) ?? $html;
}

function appendIfMissing(string $html, string $block, string $needle): string
{
    return ($block !== '' && !str_contains($html, $needle)) ? $html . $block : $html;
}

function prependIfMissing(string $html, string $block, string $needle): string
{
    return ($block !== '' && !str_contains($html, $needle)) ? $block . $html : $html;
}

$updated = 0;
foreach ($config['articles'] as $article) {
    $id = (int) ($article['id'] ?? 0);
    $news = News::query()->find($id);
    if (!$news) {
        fwrite(STDERR, "Missing news id={$id}\n");
        exit(1);
    }

    foreach (['ru', 'en'] as $loc) {
        $text = (string) $news->getTranslation('text', $loc);
        if (!empty($article['strip_data_uri_images'])) {
            $text = stripDataUriImages($text);
        }
        if (!empty($article['demote_h1_en']) && $loc === 'en') {
            $text = demoteH1($text);
        }
        if ($loc === 'ru' && !empty($article['add_guide_strip'])) {
            $strip = $article['add_guide_strip'] === 'usdt_trc20' ? $guideTrc20 : $guideRub;
            $text = prependIfMissing($text, $strip, 'news-seo9d4-guide');
        }
        if (!empty($article['add_trust_strip'])) {
            $text = appendIfMissing($text, $loc === 'ru' ? $trustRu : $trustEn, 'news-seo9d3-trust');
        }
        $news->setTranslation('text', $loc, $text);
    }

    if (!empty($article['title_en'])) {
        $news->setTranslation('name', 'en', (string) $article['title_en']);
    }

    $news->save();

    $h1Ru = (string) $news->getTranslation('name', 'ru');
    $textRu = (string) $news->getTranslation('text', 'ru');
    $snapRu = '<div class="news-seo9d3-ssr"><h1 class="text-2xl font-semibold mb-4">' . htmlspecialchars($h1Ru, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h1>' . $textRu . '</div>';
    file_put_contents("{$snapshotDir}/{$id}.ru.html", $snapRu);
    file_put_contents("{$snapshotDirPublic}/{$id}.ru.html", $snapRu);

    $enBody = (string) $news->getTranslation('text', 'en');
    if ($enBody !== '') {
        $h1En = (string) $news->getTranslation('name', 'en');
        $snapEn = '<div class="news-seo9d3-ssr"><h1 class="text-2xl font-semibold mb-4">' . htmlspecialchars($h1En, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h1>' . $enBody . '</div>';
        file_put_contents("{$snapshotDir}/{$id}.en.html", $snapEn);
        file_put_contents("{$snapshotDirPublic}/{$id}.en.html", $snapEn);
    }

    echo "OK news_id={$id} action=" . ($article['action'] ?? 'POLISH') . "\n";
    $updated++;
}

echo "SUMMARY updated={$updated}\n";
