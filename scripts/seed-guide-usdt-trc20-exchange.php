#!/usr/bin/env php
<?php
/**
 * SEO-EN-3 — Seed CMS page for /en/guides/usdt-trc20-exchange
 * Idempotent: updates existing page by slug.
 */
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Page;

$contentPath = __DIR__ . '/../storage/app/seo/content/guide-usdt-trc20-exchange.en.html';
if (!is_readable($contentPath)) {
    fwrite(STDERR, "Missing content file: {$contentPath}\n");
    exit(1);
}

$content = file_get_contents($contentPath);
if ($content === false || strlen($content) < 1000) {
    fwrite(STDERR, "Content file too short or unreadable\n");
    exit(1);
}

$page = Page::query()->where('page_slug', 'usdt-trc20-exchange')->first();
if (!$page) {
    $page = new Page();
    $page->page_slug = 'usdt-trc20-exchange';
    $page->user_id = 1;
    $page->group_id = null;
    $page->sort_order = 0;
}

$page->is_active = true;
$page->setTranslation('page_title', 'en', 'USDT TRC20 Exchange Guide');
$page->setTranslation('page_headline', 'en', 'USDT TRC20 Exchange Guide');
$page->setTranslation('page_content', 'en', $content);
$page->save();

echo "OK page_id={$page->page_id} slug=usdt-trc20-exchange content_bytes=" . strlen($content) . "\n";
