#!/usr/bin/env php
<?php
/**
 * Phase 2 duplicate cleanup — menu/footer consolidation (news → blog hub).
 * Usage: php8.4 scripts/seo/apply-duplicate-cleanup-phase2.php
 */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\LinksFooter;
use App\Models\Menu;

$changes = [];

foreach (Menu::query()->where('slug', '/news')->where('parent_id', 0)->get() as $menu) {
    if ((int) $menu->status !== 0) {
        $menu->status = 0;
        $menu->save();
        $changes[] = "menu#{$menu->id} /news disabled";
    }
}

foreach (LinksFooter::query()->get() as $link) {
    $urls = $link->getTranslations('url');
    $isNews = false;
    foreach ($urls as $url) {
        if ($url === '/news' || str_ends_with((string) $url, '/news')) {
            $isNews = true;
            break;
        }
    }
    if (!$isNews) {
        continue;
    }
    if ((int) ($link->status ?? 1) !== 0) {
        $link->status = 0;
        $link->save();
        $changes[] = "footer#{$link->id} /news disabled";
    }
}

if ($changes === []) {
    echo "OK: news menu/footer already consolidated.\n";
} else {
    echo "Applied:\n";
    foreach ($changes as $line) {
        echo " - {$line}\n";
    }
}
