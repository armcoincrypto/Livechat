#!/usr/bin/env php
<?php
/**
 * Idempotent DB updates for public client pages visibility (partners/contests/blog).
 * Usage: php8.4 scripts/seo/apply-public-client-pages-visibility.php
 */

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\LinksFooter;
use App\Models\Menu;

$changes = [];

// Fix footer "Партнерам" pointing at private /account/partners.
$partnersFooter = LinksFooter::find(9);
if ($partnersFooter) {
    $urls = $partnersFooter->getTranslations('url');
    if (($urls['ru'] ?? '') !== '/partners') {
        $partnersFooter->setTranslations('url', [
            'ru' => '/partners',
            'en' => '/partners',
            'zh' => '/partners',
        ]);
        $partnersFooter->save();
        $changes[] = 'footer#9 url -> /partners';
    }
}

$footerAdds = [
    ['group' => 1, 'name' => ['ru' => 'Блог', 'en' => 'Blog', 'zh' => '博客'], 'url' => '/blog', 'sort' => 2],
    ['group' => 1, 'name' => ['ru' => 'Конкурсы', 'en' => 'Contests', 'zh' => '竞赛'], 'url' => '/contests', 'sort' => 3],
    ['group' => 2, 'name' => ['ru' => 'Блог', 'en' => 'Blog', 'zh' => '博客'], 'url' => '/blog', 'sort' => 1],
    ['group' => 2, 'name' => ['ru' => 'Конкурсы', 'en' => 'Contests', 'zh' => '竞赛'], 'url' => '/contests', 'sort' => 2],
];

foreach ($footerAdds as $item) {
    $exists = LinksFooter::query()
        ->where('id_group', $item['group'])
        ->whereJsonContainsLocale('url', 'ru', $item['url'])
        ->exists();

    if (!$exists) {
        $link = new LinksFooter();
        $link->id_group = $item['group'];
        $link->sorting = $item['sort'];
        $link->is_blank = 0;
        $link->status = 1;
        $link->setTranslations('name', $item['name']);
        $link->setTranslations('url', [
            'ru' => $item['url'],
            'en' => $item['url'],
            'zh' => $item['url'],
        ]);
        $link->save();
        $changes[] = "footer group {$item['group']} +{$item['url']}";
    }
}

$menuAdds = [
    ['slug' => '/contests', 'name' => ['ru' => 'Конкурсы', 'en' => 'Contests', 'zh' => '竞赛'], 'sort' => 4],
    ['slug' => '/blog', 'name' => ['ru' => 'Блог', 'en' => 'Blog', 'zh' => '博客'], 'sort' => 5],
];

foreach ($menuAdds as $item) {
    $exists = Menu::query()->where('slug', $item['slug'])->where('parent_id', 0)->first();
    if (!$exists) {
        $menu = new Menu();
        $menu->slug = $item['slug'];
        $menu->sorting = $item['sort'];
        $menu->status = 1;
        $menu->parent_id = 0;
        $menu->setTranslations('name', $item['name']);
        $menu->save();
        $changes[] = "menu +{$item['slug']}";
    } elseif ((int) $exists->status !== 1) {
        $exists->status = 1;
        $exists->save();
        $changes[] = "menu enabled {$item['slug']}";
    }
}

if ($changes === []) {
    echo "OK: already applied, no DB changes.\n";
} else {
    echo "Applied:\n";
    foreach ($changes as $line) {
        echo " - {$line}\n";
    }
}
