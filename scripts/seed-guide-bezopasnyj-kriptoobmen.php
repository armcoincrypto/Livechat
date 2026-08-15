#!/usr/bin/env php
<?php
/**
 * SEO-AUTHORITY-2 — Seed CMS page for /ru/guides/bezopasnyj-kriptoobmen
 */
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Page;

$contentPath = __DIR__ . '/../storage/app/seo/content/guide-bezopasnyj-kriptoobmen.ru.html';
if (!is_readable($contentPath)) {
    fwrite(STDERR, "Missing content file: {$contentPath}\n");
    exit(1);
}

$content = file_get_contents($contentPath);
if ($content === false || strlen($content) < 2000) {
    fwrite(STDERR, "Content file too short or unreadable\n");
    exit(1);
}

$page = Page::query()->where('page_slug', 'bezopasnyj-kriptoobmen')->first();
if (!$page) {
    $page = new Page();
    $page->page_slug = 'bezopasnyj-kriptoobmen';
    $page->user_id = 1;
    $page->group_id = null;
    $page->sort_order = 0;
}

$page->is_active = true;
$page->setTranslation('page_title', 'ru', 'Безопасный обмен криптовалют');
$page->setTranslation('page_headline', 'ru', 'Как безопасно обменять криптовалюту через Exswaping');
$page->setTranslation('page_content', 'ru', $content);
$page->save();

echo "OK page_id={$page->page_id} slug=bezopasnyj-kriptoobmen content_bytes=" . strlen($content) . "\n";
