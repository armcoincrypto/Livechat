<?php

declare(strict_types=1);

/**
 * Focused Didit webhook signature self-test. Run from Laravel root:
 *   php8.4 packages/KYCPlugin/Drivers/Didit/DiditWebhookSignature.selftest.php
 */
$root = dirname(__DIR__, 4);
require $root . "/vendor/autoload.php";
$app = require $root . "/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use iEXPackages\KYCPlugin\Drivers\Didit\DiditWebhookSignature;

$pass = 0;
$fail = 0;
$check = static function (string $name, bool $cond) use (&$pass, &$fail): void {
    if ($cond) {
        $pass++;
        echo "PASS {$name}\n";
    } else {
        $fail++;
        echo "FAIL {$name}\n";
    }
};

$secret = "test_webhook_secret_value_for_unit";
$body = [
    "webhook_type" => "status.updated",
    "session_id" => "sess_abc",
    "status" => "Approved",
    "timestamp" => 1774970000,
    "decision" => ["score" => 1.0, "nested" => ["z" => 1, "a" => 2]],
    "vendor_data" => "ref-1",
];
$raw = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: "";
$v2 = hash_hmac("sha256", DiditWebhookSignature::canonicalJson($body), $secret);
$rawSig = hash_hmac("sha256", $raw, $secret);
$simple = hash_hmac("sha256", "1774970000:sess_abc:Approved:status.updated", $secret);

$check("v2_valid", DiditWebhookSignature::isValid($secret, $raw, $body, $v2, null, null));
$check("v2_rejects_tamper", !DiditWebhookSignature::isValid($secret, $raw, $body, "deadbeef", null, null));
$check("raw_valid", DiditWebhookSignature::isValid($secret, $raw, $body, null, $rawSig, null));
$check("simple_valid", DiditWebhookSignature::isValid($secret, $raw, $body, null, null, $simple));
$bodyNoTs = $body;
unset($bodyNoTs["timestamp"]);
$check(
    "simple_uses_x_timestamp",
    DiditWebhookSignature::isValid($secret, $raw, $bodyNoTs, null, null, $simple, "1774970000"),
);
$check("wrong_secret_fails", !DiditWebhookSignature::isValid("other", $raw, $body, $v2, $rawSig, $simple));
$canonical = DiditWebhookSignature::canonicalJson($body);
$check("canonical_shortens_float", str_contains($canonical, '"score":1') && !str_contains($canonical, '"score":1.0'));
$check("canonical_sorts_nested", strpos($canonical, '"a":2') < strpos($canonical, '"z":1'));

echo "SUMMARY PASS={$pass} FAIL={$fail}\n";
exit($fail > 0 ? 1 : 0);
