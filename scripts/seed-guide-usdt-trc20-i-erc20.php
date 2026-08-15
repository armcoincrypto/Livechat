#!/usr/bin/env php
<?php
/**
 * SEO-RU-10A — Seed CMS page for /ru/guides/usdt-trc20-i-erc20
 */
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Page;

$contentPath = __DIR__ . '/../storage/app/seo/content/guide-usdt-trc20-i-erc20.ru.html';
if (!is_readable($contentPath)) {
    fwrite(STDERR, "Missing content file: {$contentPath}\n");
    exit(1);
}

$content = file_get_contents($contentPath);
if ($content === false || strlen($content) < 1000) {
    fwrite(STDERR, "Content file too short or unreadable\n");
    exit(1);
}

$page = Page::query()->where('page_slug', 'usdt-trc20-i-erc20')->first();
if (!$page) {
    $page = new Page();
    $page->page_slug = 'usdt-trc20-i-erc20';
    $page->user_id = 1;
    $page->group_id = null;
    $page->sort_order = 0;
}

$page->is_active = true;
$page->setTranslation('page_title', 'ru', 'USDT TRC20 и ERC20');
$page->setTranslation('page_headline', 'ru', 'USDT TRC20 и ERC20: в чём разница и что выбрать');
$page->setTranslation('page_content', 'ru', $content);
$page->save();

echo "OK page_id={$page->page_id} slug=usdt-trc20-i-erc20 content_bytes=" . strlen($content) . "\n";
