#!/usr/bin/env php
<?php
/**
 * P9.2B — Seed CMS page for /ru/guides/usdt-trc20-na-t-bank
 * Idempotent: updates existing page by slug.
 */
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Page;

$contentPath = __DIR__ . '/../storage/app/seo/content/guide-usdt-trc20-na-t-bank.ru.html';
if (!is_readable($contentPath)) {
    fwrite(STDERR, "Missing content file: {$contentPath}\n");
    exit(1);
}

$content = file_get_contents($contentPath);
if ($content === false || strlen($content) < 1000) {
    fwrite(STDERR, "Content file too short or unreadable\n");
    exit(1);
}

$page = Page::query()->where('page_slug', 'usdt-trc20-na-t-bank')->first();
if (!$page) {
    $page = new Page();
    $page->page_slug = 'usdt-trc20-na-t-bank';
    $page->user_id = 1;
    $page->group_id = null;
    $page->sort_order = 0;
}

$page->is_active = true;
$page->setTranslation('page_title', 'ru', 'Обмен USDT TRC20 на Т‑Банк');
$page->setTranslation('page_headline', 'ru', 'Обмен USDT TRC20 на Т‑Банк — вывод Tether на карту Т‑Банка');
$page->setTranslation('page_content', 'ru', $content);
$page->save();

echo "OK page_id={$page->page_id} slug=usdt-trc20-na-t-bank content_bytes=" . strlen($content) . "\n";
