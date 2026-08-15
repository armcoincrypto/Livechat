<?php

declare(strict_types=1);

/**
 * KYC Operations Center self-test (read-mostly + safe reconcile).
 * Run: php8.4 packages/KYCPlugin/Services/KycOps.selftest.php
 */

require __DIR__ . '/../../../vendor/autoload.php';
$app = require __DIR__ . '/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\KycProviderSession;
use App\Models\User;
use App\Models\UserVerification;
use App\Services\Administrator\KycOps\KycOpsService;

$pass = 0;
$fail = 0;
$assert = static function (string $name, bool $ok) use (&$pass, &$fail): void {
    echo ($ok ? 'PASS' : 'FAIL')."  $name\n";
    if ($ok) {
        $pass++;
    } else {
        $fail++;
    }
};

$ops = app(KycOpsService::class);
$dash = $ops->dashboard();
$assert('dashboard_has_provider', isset($dash['configured_provider']));
$assert('dashboard_no_secret_values', ! str_contains(json_encode($dash), (string) config('kyc.didit.api_key')));

$list = $ops->listCases(['page' => 1], 25);
$assert('list_paginated', $list->total() >= 0);

$manual = UserVerification::query()->where('status', 0)->first();
if ($manual) {
    $d = $ops->caseDetail('manual:'.$manual->id);
    $assert('manual_detail', ($d['provider']['current'] ?? '') === 'manual');
    $assert('manual_no_reconcile_as_provider_edit', in_array('open_manual_queue', $d['safe_actions'], true));
}

$didit = KycProviderSession::query()->where('provider', 'didit')->orderByDesc('id')->first();
if ($didit) {
    $d = $ops->caseDetail('didit:'.$didit->id);
    $assert('didit_detail_masked_session', is_string($d['provider']['session_id_masked'] ?? null));
    $assert('didit_timeline_present', is_array($d['timeline']));
    $before = KycProviderSession::query()->where('user_id', (int) $didit->user_id)->count();
    $r = $ops->reconcile('didit:'.$didit->id, 0);
    $after = KycProviderSession::query()->where('user_id', (int) $didit->user_id)->count();
    $assert('reconcile_ok', ! empty($r['ok']));
    $assert('reconcile_no_new_session', $before === $after);
}

$att = $ops->attentionItems(50);
$assert('attention_is_array', is_array($att));

$diag = $ops->diagnostics();
$assert('diagnostics_webhook', isset($diag['webhook']['signature_status']));
$assert('diagnostics_no_raw_secret', ! str_contains(json_encode($diag), (string) config('kyc.didit.webhook_secret')));

foreach ([353, 1927, 1928, 920970004] as $uid) {
    $u = User::query()->find($uid);
    $assert("protected_user_{$uid}_present", $u !== null);
}

echo "SUMMARY PASS=$pass FAIL=$fail\n";
exit($fail === 0 ? 0 : 1);
