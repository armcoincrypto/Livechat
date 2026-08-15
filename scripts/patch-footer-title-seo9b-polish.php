#!/usr/bin/env php
<?php
/**
 * SEO-9B polish — safer RU footer title (no "биржа" claim).
 * Updates dynamic_config_settings.language.input_footer_title (content only, not schema).
 */
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

$newRu = 'Exswaping — сервис обмена криптовалют';
$oldRu = 'Exswaping Криптовалютная Биржа';

$row = DB::table('dynamic_config_settings')
    ->where('scope_type', 'language')
    ->where('scope_id', 1)
    ->where('key', 'language.input_footer_title')
    ->first();

if (!$row) {
    fwrite(STDERR, "Missing dynamic_config_settings row for language.input_footer_title\n");
    exit(1);
}

$value = json_decode((string) $row->value, true);
if (!is_array($value)) {
    fwrite(STDERR, "Invalid JSON in language.input_footer_title\n");
    exit(1);
}

if (($value['ru'] ?? '') === $newRu) {
    echo "OK footer RU already: {$newRu}\n";
    exit(0);
}

$value['ru'] = $newRu;

DB::table('dynamic_config_settings')
    ->where('id', $row->id)
    ->update([
        'value' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'updated_at' => now(),
    ]);

Cache::flush();

// Fresh process read (preload cache in long-lived PHP-FPM workers clears on reload)
echo "OK footer RU updated in DB: {$oldRu} -> {$newRu}\n";
echo "NOTE: reload PHP-FPM / app workers so APP_INIT_DATA picks up the new title.\n";
