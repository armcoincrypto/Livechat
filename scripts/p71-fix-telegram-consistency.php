<?php

declare(strict_types=1);

/**
 * P7.1 — Standardize Telegram support handle on contacts social links.
 *
 * Verified primary support: t.me/exswaping (header, homepage chip, contacts group, BestChange).
 * Legacy social_reviews entry used exswapingex — updated to match.
 *
 * Usage: php8.4 scripts/p71-fix-telegram-consistency.php [--dry-run]
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$dryRun = in_array('--dry-run', $argv, true);
$backupDir = __DIR__ . '/../storage/app/p71-backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$row = DB::table('social_reviews')->where('id', 3)->first();
if (!$row) {
    fwrite(STDERR, "social_reviews id=3 not found\n");
    exit(1);
}

$backup = (array) $row;
$backup['backed_up_at'] = date('c');
file_put_contents(
    $backupDir . '/social_reviews-id3-before.json',
    json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
);

$official = 'https://t.me/exswaping';
if ($row->link === $official) {
    echo "Already standardized: {$official}\n";
    exit(0);
}

echo "Before: {$row->link}\n";
echo "After:  {$official}\n";

if ($dryRun) {
    echo "[dry-run] no DB write\n";
    exit(0);
}

DB::table('social_reviews')->where('id', 3)->update([
    'link' => $official,
    'updated_at' => now(),
]);

echo "Updated social_reviews.id=3\n";
