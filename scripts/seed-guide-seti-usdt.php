#!/usr/bin/env php
<?php
/**
 * SEO-AUTHORITY-4 — Seed CMS page for /ru/guides/seti-usdt
 */
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Page;

$contentPath = __DIR__ . '/../storage/app/seo/content/guide-seti-usdt.ru.html';
if (!is_readable($contentPath)) {
    fwrite(STDERR, "Missing content file: {$contentPath}\n");
    exit(1);
}

$content = file_get_contents($contentPath);
if ($content === false || strlen($content) < 2000) {
    fwrite(STDERR, "Content file too short or unreadable\n");
    exit(1);
}

$page = Page::query()->where('page_slug', 'seti-usdt')->first();
if (!$page) {
    $page = new Page();
    $page->page_slug = 'seti-usdt';
    $page->user_id = 1;
    $page->group_id = null;
    $page->sort_order = 0;
}

$page->is_active = true;
$page->setTranslation('page_title', 'ru', 'Сети USDT: TRC20, ERC20, BEP20');
$page->setTranslation('page_headline', 'ru', 'Экосистема сетей USDT — как выбрать TRC20, ERC20 или BEP20');
$page->setTranslation('page_content', 'ru', $content);
$page->save();

echo "OK page_id={$page->page_id} slug=seti-usdt content_bytes=" . strlen($content) . "\n";
