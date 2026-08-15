#!/usr/bin/env php
<?php
/**
 * SEO-10A — Authority flow (all articles) + Priority 1 content upgrade.
 */
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\News;

$configPath = __DIR__ . '/../storage/app/seo/news-seo10a-config.json';
$contentDir = __DIR__ . '/../storage/app/seo/content';
$snapshotDir = __DIR__ . '/../storage/app/seo/news-ssr-snapshots';
$snapshotDirPublic = '/var/www/exswaping_co_usr/data/www/exswaping.com/storage/seo/news-ssr-snapshots';

$config = json_decode((string) file_get_contents($configPath), true);
$priority1 = array_map('intval', $config['priority1_ids'] ?? []);
$clusterRu = (string) ($config['guide_strip_ru_cluster'] ?? '');
$clusterEn = (string) ($config['guide_strip_en_cluster'] ?? '');
$trustRu = (string) ($config['trust_strip_ru'] ?? '');
$trustEn = (string) ($config['trust_strip_en'] ?? '');
$markerCluster = (string) ($config['marker_cluster'] ?? 'news-seo10a-cluster');
$markerTrust = (string) ($config['marker_trust'] ?? 'news-seo10a-trust');

function stripSeoBlocks(string $html): string
{
    $patterns = [
        '/<div class="news-seo9d4-guide[^"]*"[\s\S]*?<\/div>/i',
        '/<div class="news-seo9d3-trust[^"]*"[\s\S]*?<\/div>/i',
        '/<div class="news-seo10a-trust[^"]*"[\s\S]*?<\/div>/i',
        '/<div class="news-seo10a-cluster[^"]*"[\s\S]*?<\/div>/i',
        '/<div class="news-seo9d-intro[^"]*"[\s\S]*?<\/div>/i',
        '/<div class="news-seo9d-cta[^"]*"[\s\S]*?<\/div>/i',
        '/<div class="news-seo9d3-intro[^"]*"[\s\S]*?<\/div>/i',
        '/<div class="news-seo9d3-cta[^"]*"[\s\S]*?<\/div>/i',
        '/<div class="news-seo9d1-intro[^"]*"[\s\S]*?<\/div>/i',
    ];
    foreach ($patterns as $pattern) {
        $html = preg_replace($pattern, '', $html) ?? $html;
    }
    return trim($html);
}

function prependIfMissing(string $html, string $block, string $needle): string
{
    return ($block !== '' && !str_contains($html, $needle)) ? $block . $html : $html;
}

function appendIfMissing(string $html, string $block, string $needle): string
{
    return ($block !== '' && !str_contains($html, $needle)) ? $html . $block : $html;
}

function buildText(string $body, string $cluster, string $trust, string $clusterMarker, string $trustMarker): string
{
    $body = stripSeoBlocks($body);
    $text = prependIfMissing($body, $cluster, $clusterMarker);
    return appendIfMissing($text, $trust, $trustMarker);
}

function writeSnapshot(int $id, string $loc, string $h1, string $text, string $dir, string $dirPublic): void
{
    $snap = '<div class="news-seo9d3-ssr"><h1 class="text-2xl font-semibold mb-4">'
        . htmlspecialchars($h1, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</h1>' . $text . '</div>';
    file_put_contents("{$dir}/{$id}.{$loc}.html", $snap);
    file_put_contents("{$dirPublic}/{$id}.{$loc}.html", $snap);
}

$updated = 0;
foreach ($config['articles'] as $article) {
    $id = (int) ($article['id'] ?? 0);
    $news = News::query()->find($id);
    if (!$news) {
        fwrite(STDERR, "Missing news id={$id}\n");
        exit(1);
    }

    $isP1 = in_array($id, $priority1, true);

    foreach (['ru', 'en'] as $loc) {
        $cluster = $loc === 'ru' ? $clusterRu : $clusterEn;
        $trust = $loc === 'ru' ? $trustRu : $trustEn;

        if ($isP1) {
            $contentPath = "{$contentDir}/news-{$id}-{$loc}.html";
            if (!is_readable($contentPath)) {
                fwrite(STDERR, "Missing content: {$contentPath}\n");
                exit(1);
            }
            $body = (string) file_get_contents($contentPath);
            $text = buildText($body, $cluster, $trust, $markerCluster, $markerTrust);
        } else {
            $text = (string) $news->getTranslation('text', $loc);
            $text = buildText($text, $cluster, $trust, $markerCluster, $markerTrust);
        }

        $news->setTranslation('text', $loc, $text);
    }

    $news->save();

    foreach (['ru', 'en'] as $loc) {
        $enBody = (string) $news->getTranslation('text', $loc);
        if ($enBody === '') {
            continue;
        }
        $h1 = (string) $news->getTranslation('name', $loc);
        writeSnapshot($id, $loc, $h1, $enBody, $snapshotDir, $snapshotDirPublic);
    }

    echo 'OK news_id=' . $id . ' p1=' . ($isP1 ? 'yes' : 'flow-only') . "\n";
    $updated++;
}

echo "SUMMARY updated={$updated}\n";
