#!/usr/bin/env php
<?php
/**
 * P9.2D — Seed CMS page for /ru/guides/kak-rabotayut-rezervy-obmennikov
 * Idempotent: updates existing page by slug.
 */
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Page;

$contentPath = __DIR__ . '/../storage/app/seo/content/guide-kak-rabotayut-rezervy-obmennikov.ru.html';
if (!is_readable($contentPath)) {
    fwrite(STDERR, "Missing content file: {$contentPath}\n");
    exit(1);
}

$content = file_get_contents($contentPath);
if ($content === false || strlen($content) < 1000) {
    fwrite(STDERR, "Content file too short or unreadable\n");
    exit(1);
}

$page = Page::query()->where('page_slug', 'kak-rabotayut-rezervy-obmennikov')->first();
if (!$page) {
    $page = new Page();
    $page->page_slug = 'kak-rabotayut-rezervy-obmennikov';
    $page->user_id = 1;
    $page->group_id = null;
    $page->sort_order = 0;
}

$page->is_active = true;
$page->setTranslation('page_title', 'ru', 'Как работают резервы обменников');
$page->setTranslation('page_headline', 'ru', 'Как работают резервы обменников — ликвидность и оценка сервиса');
$page->setTranslation('page_content', 'ru', $content);
$page->save();

echo "OK page_id={$page->page_id} slug=kak-rabotayut-rezervy-obmennikov content_bytes=" . strlen($content) . "\n";
