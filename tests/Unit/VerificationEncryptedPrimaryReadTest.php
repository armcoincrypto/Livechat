<?php

declare(strict_types=1);

/**
 * CARDS_VERIFICATION_ENCRYPTED_PRIMARY_READ certification.
 * No identifier values printed.
 */
require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\VerificationCard;
use App\Services\Verification\VerificationIdentifierVault;
use Illuminate\Support\Facades\DB;

$failures = 0;
function assertTrue(bool $ok, string $msg): void
{
    global $failures;
    echo ($ok ? 'OK' : 'FAIL').": {$msg}\n";
    if (! $ok) {
        $failures++;
    }
}

$vault = app(VerificationIdentifierVault::class);
$vault->resetResolvePathCounts();

assertTrue((bool) config('verification.plaintext_fallback_enabled'), 'fallback still enabled');
assertTrue((bool) config('verification.encrypted_write_enabled'), 'encrypted writes still enabled');

$eligible = VerificationCard::query()
    ->where(function ($q) {
        $q->where(function ($q2) {
            $q2->whereNotNull('card_number')->where('card_number', '!=', '');
        })->orWhere(function ($q2) {
            $q2->whereNotNull('card_number_string')->where('card_number_string', '!=', '');
        });
    })
    ->count();

$withCipher = VerificationCard::query()
    ->whereNotNull('card_number_ciphertext')
    ->where('card_number_ciphertext', '!=', '')
    ->count();

assertTrue($eligible === $withCipher, "coverage eligible={$eligible} ciphertext={$withCipher}");

$cipherReads = 0;
$mismatch = 0;
$legacyOnlyResolve = 0;

VerificationCard::query()
    ->whereNotNull('card_number_ciphertext')
    ->where('card_number_ciphertext', '!=', '')
    ->orderBy('id')
    ->chunkById(50, function ($rows) use ($vault, &$cipherReads, &$mismatch, &$legacyOnlyResolve) {
        foreach ($rows as $row) {
            $resolved = $row->resolvedCardNumber();
            $legacy = $vault->normalize((string) $row->card_number);
            if ($resolved === null) {
                $mismatch++;
                continue;
            }
            $cipherReads++;
            // Ciphertext-first: even with wrong legacy hint, resolve uses ciphertext
            $forced = $vault->resolve((string) $row->card_number_ciphertext, '0000WRONGLEGACY');
            if ($forced === null || ! hash_equals($vault->normalize($resolved), $vault->normalize($forced))) {
                $mismatch++;
            }
            if ($legacy !== '' && ! hash_equals($vault->normalize($resolved), $legacy)) {
                $mismatch++;
            }
        }
    });

assertTrue($mismatch === 0, "ciphertext-first resolve mismatches={$mismatch}");
assertTrue($cipherReads === $withCipher, "cipher reads={$cipherReads}");

$counts = $vault->resolvePathCounts();
assertTrue(($counts['ciphertext'] ?? 0) >= $withCipher, 'metrics recorded ciphertext path');
assertTrue(($counts['plaintext_fallback'] ?? 0) === 0, 'no fallback while resolving encrypted rows');

// Controlled fallback measurement (synthetic, no DB write)
$vault->resetResolvePathCounts();
$fb = $vault->resolve(null, '4111111111111111');
assertTrue($fb === '4111111111111111', 'fallback still works when enabled');
$fbCounts = $vault->resolvePathCounts();
assertTrue(($fbCounts['plaintext_fallback'] ?? 0) === 1, 'fallback metric increments');

// Exact-match uses lookup OR legacy scope
$row = VerificationCard::query()
    ->whereNotNull('card_number_ciphertext')
    ->where('card_number_ciphertext', '!=', '')
    ->orderBy('id')
    ->first();
assertTrue($row !== null, 'sample row present');
$norm = $vault->normalize((string) $row->card_number);
assertTrue(
    VerificationCard::query()->whereIdentifier($norm)->where('id', $row->id)->exists(),
    'whereIdentifier finds encrypted row'
);

// Admin filter path: last4
$last4 = (string) $row->card_number_last4;
assertTrue(
    $last4 !== '' && VerificationCard::filter(['card_number' => $last4])->where('id', $row->id)->exists(),
    'admin filter last4 match'
);
assertTrue(
    VerificationCard::filter(['card_number' => $norm, 'checkbox_card_number' => 1])->where('id', $row->id)->exists(),
    'admin filter exact via whereIdentifier'
);

echo 'resolve_counts_after_encrypted_pass='.json_encode($counts)."\n";
echo 'fallback_flag=ON plaintext_writes=ON (unchanged)'."\n";

echo $failures === 0 ? "\nSUMMARY PASS\n" : "\nSUMMARY FAIL count={$failures}\n";
exit($failures === 0 ? 0 : 1);
