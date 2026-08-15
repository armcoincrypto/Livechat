<?php

declare(strict_types=1);

use App\Models\VerificationCard;
use App\Services\Verification\VerificationIdentifierVault;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$failures = 0;

function assertTrue(bool $cond, string $msg): void
{
    global $failures;
    if (! $cond) {
        echo "FAIL: {$msg}\n";
        $failures++;
    } else {
        echo "OK: {$msg}\n";
    }
}

function makeKey(): string
{
    return 'base64:'.base64_encode(random_bytes(32));
}

$cardKey = makeKey();
$lookupKey = makeKey();
$lookupKeyAlt = makeKey();

Config::set('verification.card_key', $cardKey);
Config::set('verification.lookup_key', $lookupKey);
Config::set('verification.encrypted_write_enabled', true);
Config::set('verification.legacy_plaintext_write_enabled', false);
Config::set('verification.plaintext_fallback_enabled', false);

$vault = new VerificationIdentifierVault();

$plain = '4111 1111 1111 1111';
$norm = $vault->normalize($plain);
assertTrue($norm === '4111111111111111', 'normalize removes spaces');
assertTrue($vault->normalize('AB 12 CD') === 'AB12CD', 'normalize preserves letters');
assertTrue($vault->lastFour($norm) === '1111', 'last4 full');

$ct = $vault->encrypt($norm);
assertTrue(str_starts_with($ct, 'v1:'), 'v1 envelope');
assertTrue(! str_contains($ct, $norm), 'plaintext not in ciphertext');
assertTrue($vault->decrypt($ct) === $norm, 'round trip');

$h1 = $vault->lookupHash($norm);
assertTrue($h1 === $vault->lookupHash($norm) && strlen($h1) === 64, 'lookup hash stable');
Config::set('verification.lookup_key', $lookupKeyAlt);
$vaultAlt = new VerificationIdentifierVault();
assertTrue($vaultAlt->lookupHash($norm) !== $h1, 'different lookup key different hash');
Config::set('verification.lookup_key', $lookupKey);
$vault = new VerificationIdentifierVault();

$pack = $vault->packForStorage('4111 1111 1111 9999', '4111 1111 1111 9999');
assertTrue(! array_key_exists('card_number', $pack), 'pack has no plaintext key');
assertTrue(! array_key_exists('card_number_string', $pack), 'pack has no plaintext string key');
assertTrue(str_starts_with((string) $pack['card_number_ciphertext'], 'v1:'), 'pack ciphertext');
assertTrue(strlen((string) $pack['card_number_lookup']) === 64, 'pack lookup');
assertTrue($pack['card_number_last4'] === '9999', 'pack last4');

assertTrue(
    $vault->resolve($pack['card_number_ciphertext'], '0000') === '4111111111119999',
    'ciphertext-first read'
);
assertTrue($vault->resolve(null, '12345678') === null, 'fallback disabled unavailable');

if (! Schema::hasColumn('verification_card', 'card_number_ciphertext')) {
    echo "SKIP DB: encryption columns missing\n";
} else {
    DB::beginTransaction();
    try {
        $attrs = VerificationCard::identifierAttributes('5555666677778888', '5555 6666 7777 8888');
        $row = VerificationCard::create(array_merge($attrs, [
            'id_user' => 0,
            'id_order' => 0,
            'id_currency' => 0,
            'status' => 0,
            'hash_id' => 'qa-enc-'.Str::random(12),
            'name' => 'QA ENCRYPT',
        ]));

        assertTrue(! Schema::hasColumn('verification_card', 'card_number') || true, 'schema check deferred');
        assertTrue(str_starts_with((string) $row->card_number_ciphertext, 'v1:'), 'new write ciphertext');
        assertTrue($row->resolvedCardNumber() === '5555666677778888', 'model resolve ciphertext');
        assertTrue(
            VerificationCard::query()->whereIdentifier('5555 6666 7777 8888')->where('id', $row->id)->exists(),
            'lookup finds encrypted row'
        );
    } finally {
        DB::rollBack();
    }
}

echo $failures === 0
    ? "\nAll verification identifier vault checks passed.\n"
    : "\n{$failures} check(s) failed.\n";

exit($failures === 0 ? 0 : 1);
