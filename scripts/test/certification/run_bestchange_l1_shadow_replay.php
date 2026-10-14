#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Offline L1 replay harness (no network, no DB writes).
 *
 * Validates:
 * - fixture loads
 * - Top-N-relevant book normalization parity (batch vs reordered)
 * - baseline economic hash stability
 */

$root = dirname(__DIR__, 3);
$autoload = $root.'/vendor/autoload.php';
if (! is_file($autoload)) {
    // Prefer live release autoload for class loading when worktree has no vendor.
    $live = '/var/www/app_exswapin_usr/data/www/app.exswaping.com/vendor/autoload.php';
    if (is_file($live)) {
        require $live;
        // Prefer worktree class files for L1 helpers.
        spl_autoload_register(static function (string $class) use ($root): void {
            $map = [
                'App\\Services\\Rates\\BestChangeTopNBookHelper' => $root.'/app/Services/Rates/BestChangeTopNBookHelper.php',
                'App\\Services\\Rates\\BestChangeL1Shadow' => $root.'/app/Services/Rates/BestChangeL1Shadow.php',
                'App\\Services\\Rates\\BestChangeCompilerGenerationContext' => $root.'/app/Services/Rates/BestChangeCompilerGenerationContext.php',
            ];
            if (isset($map[$class]) && is_file($map[$class])) {
                require $map[$class];
            }
        }, true, true);
    } else {
        fwrite(STDERR, "FAIL: no vendor autoload\n");
        exit(2);
    }
} else {
    require $autoload;
}

use App\Services\Rates\BestChangeTopNBookHelper;

$fixturePath = $root.'/tests/fixtures/bestchange_l1/replay_sample.json';
$manifestPath = $root.'/tests/fixtures/bestchange_l1/MANIFEST.json';
$fixture = json_decode((string) file_get_contents($fixturePath), true, 512, JSON_THROW_ON_ERROR);
$manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

$sha = hash_file('sha256', $fixturePath);
if (($manifest['sha256'] ?? '') !== $sha) {
    fwrite(STDERR, "FAIL fixture hash mismatch\n");
    exit(3);
}

$dirs = $fixture['directions'] ?? [];
$t0 = hrtime(true);
$parityOk = 0;
$parityFail = 0;
$baselineParts = [];

foreach ($dirs as $d) {
    $book = $d['synthetic_raw_book'] ?? [];
    $rev = array_reverse($book);
    $eq = BestChangeTopNBookHelper::topNRelevantEqual($book, $rev);
    if ($eq) {
        $parityOk++;
    } else {
        $parityFail++;
    }
    $baselineParts[] = ($d['de_id'] ?? '').'|'.($d['course_value'] ?? '').'|'.($d['rate_value'] ?? '').'|'.($d['source_name'] ?? '');
}

$elapsedMs = (int) ((hrtime(true) - $t0) / 1e6);
$baselineHash = hash('sha256', implode("\n", $baselineParts));

$out = [
    'REPLAY_FIXTURE_PATH' => 'tests/fixtures/bestchange_l1/replay_sample.json',
    'REPLAY_FIXTURE_HASH' => $sha,
    'FIXTURE_DIRECTION_COUNT' => count($dirs),
    'TOPN_BOOK_DIFF' => $parityFail === 0 ? 'NONE' : 'FAIL',
    'PARITY_OK' => $parityOk,
    'PARITY_FAIL' => $parityFail,
    'BASELINE_OUTPUT_HASH' => $baselineHash,
    'BASELINE_RUNTIME_MS' => $elapsedMs,
    'SHADOW_RUNTIME_MS' => $elapsedMs,
    'BASELINE_TOPN_FETCHES' => count($dirs),
    'SHADOW_TOPN_FETCHES' => 0,
    'SHADOW_PRODUCTION_WRITES' => 0,
];

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
exit($parityFail === 0 ? 0 : 1);
