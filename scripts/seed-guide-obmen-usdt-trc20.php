#!/usr/bin/env php
<?php
/**
 * SEO-9E — Seed CMS page for /ru/guides/obmen-usdt-trc20
 * Idempotent: updates existing page by slug.
 */
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Page;

$contentPath = __DIR__ . '/../storage/app/seo/content/guide-obmen-usdt-trc20.ru.html';
if (!is_readable($contentPath)) {
    fwrite(STDERR, "Missing content file: {$contentPath}\n");
    exit(1);
}

$content = file_get_contents($contentPath);
if ($content === false || strlen($content) < 1000) {
    fwrite(STDERR, "Content file too short or unreadable\n");
    exit(1);
}

$page = Page::query()->where('page_slug', 'obmen-usdt-trc20')->first();
if (!$page) {
    $page = new Page();
    $page->page_slug = 'obmen-usdt-trc20';
    $page->user_id = 1;
    $page->group_id = null;
    $page->sort_order = 0;
}

$page->is_active = true;
$page->setTranslation('page_title', 'ru', 'Обмен USDT TRC20');
$page->setTranslation('page_headline', 'ru', 'Tether в сети TRON: обмен USDT TRC20 через Exswaping');
$page->setTranslation('page_content', 'ru', $content);
$page->save();

echo "OK page_id={$page->page_id} slug=obmen-usdt-trc20 content_bytes=" . strlen($content) . "\n";
