#!/usr/bin/env php
<?php
/**
 * SEO-9F — Seed CMS page for /ru/guides/obmen-usdt-na-kartu
 * Idempotent: updates existing page by slug.
 */
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Page;

$contentPath = __DIR__ . '/../storage/app/seo/content/guide-obmen-usdt-na-kartu.ru.html';
if (!is_readable($contentPath)) {
    fwrite(STDERR, "Missing content file: {$contentPath}\n");
    exit(1);
}

$content = file_get_contents($contentPath);
if ($content === false || strlen($content) < 1000) {
    fwrite(STDERR, "Content file too short or unreadable\n");
    exit(1);
}

$page = Page::query()->where('page_slug', 'obmen-usdt-na-kartu')->first();
if (!$page) {
    $page = new Page();
    $page->page_slug = 'obmen-usdt-na-kartu';
    $page->user_id = 1;
    $page->group_id = null;
    $page->sort_order = 0;
}

$page->is_active = true;
$page->setTranslation('page_title', 'ru', 'Обмен USDT на карту');
$page->setTranslation('page_headline', 'ru', 'Обмен USDT на банковскую карту через Exswaping');
$page->setTranslation('page_content', 'ru', $content);
$page->save();

echo "OK page_id={$page->page_id} slug=obmen-usdt-na-kartu content_bytes=" . strlen($content) . "\n";
