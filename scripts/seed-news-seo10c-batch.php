#!/usr/bin/env php
<?php
/**
 * SEO-10C — Priority 3 legacy content upgrade (CMS + snapshots).
 */
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\News;

$configPath = __DIR__ . '/../storage/app/seo/news-seo10c-config.json';
$stripConfigPath = __DIR__ . '/../storage/app/seo/news-seo10a-config.json';
$contentDir = __DIR__ . '/../storage/app/seo/content';
$snapshotDir = __DIR__ . '/../storage/app/seo/news-ssr-snapshots';
$snapshotDirPublic = '/var/www/exswaping_co_usr/data/www/exswaping.com/storage/seo/news-ssr-snapshots';

$config = json_decode((string) file_get_contents($configPath), true);
$strips = json_decode((string) file_get_contents($stripConfigPath), true);
$priority3 = array_map('intval', $config['priority3_ids'] ?? []);
$clusterRu = (string) ($strips['guide_strip_ru_cluster'] ?? '');
$clusterEn = (string) ($strips['guide_strip_en_cluster'] ?? '');
$trustRu = (string) ($strips['trust_strip_ru'] ?? '');
$trustEn = (string) ($strips['trust_strip_en'] ?? '');
$markerCluster = (string) ($strips['marker_cluster'] ?? 'news-seo10a-cluster');
$markerTrust = (string) ($strips['marker_trust'] ?? 'news-seo10a-trust');
$bodyMarker = 'news-seo10c-body';

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
        '/<div class="news-seo10a-body[^"]*"[\s\S]*?<\/div>\s*$/i',
        '/<div class="news-seo10b-body[^"]*"[\s\S]*?<\/div>\s*$/i',
    ];
    foreach ($patterns as $pattern) {
        $html = preg_replace($pattern, '', $html) ?? $html;
    }
    return trim($html);
}

function buildText(string $body, string $cluster, string $trust, string $clusterMarker, string $trustMarker): string
{
    $body = stripSeoBlocks($body);
    $text = str_contains($body, $clusterMarker) ? $body : $cluster . $body;
    return str_contains($text, $trustMarker) ? $text : $text . $trust;
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
foreach ($priority3 as $id) {
    $news = News::query()->find($id);
    if (!$news) {
        fwrite(STDERR, "Missing news id={$id}\n");
        exit(1);
    }

    foreach (['ru', 'en'] as $loc) {
        $contentPath = "{$contentDir}/news-{$id}-{$loc}.html";
        if (!is_readable($contentPath)) {
            fwrite(STDERR, "Missing content: {$contentPath}\n");
            exit(1);
        }
        if (!str_contains((string) file_get_contents($contentPath), $bodyMarker)) {
            fwrite(STDERR, "Missing body marker in {$contentPath}\n");
            exit(1);
        }
        $body = (string) file_get_contents($contentPath);
        $cluster = $loc === 'ru' ? $clusterRu : $clusterEn;
        $trust = $loc === 'ru' ? $trustRu : $trustEn;
        $text = buildText($body, $cluster, $trust, $markerCluster, $markerTrust);
        $news->setTranslation('text', $loc, $text);
    }

    $news->save();

    foreach (['ru', 'en'] as $loc) {
        $body = (string) $news->getTranslation('text', $loc);
        if ($body === '') {
            continue;
        }
        $h1 = (string) $news->getTranslation('name', $loc);
        writeSnapshot($id, $loc, $h1, $body, $snapshotDir, $snapshotDirPublic);
    }

    $updated++;
    echo "OK news id={$id}\n";
}

echo "SUMMARY updated={$updated} ids=" . implode(',', $priority3) . "\n";
