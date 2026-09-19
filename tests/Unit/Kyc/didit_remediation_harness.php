<?php

declare(strict_types=1);

/**
 * Isolated Didit remediation certification harness.
 *
 * Does not bootstrap Laravel, does not read .env, and never touches
 * production MySQL. Run with:
 *
 *   php8.4 tests/Unit/Kyc/didit_remediation_harness.php
 */
$root = dirname(__DIR__, 3);

require $root.'/packages/KYCPlugin/Drivers/Didit/DiditStatusNormalizer.php';
require $root.'/packages/KYCPlugin/Drivers/Didit/DiditStatusTransition.php';
require $root.'/packages/KYCPlugin/Drivers/Didit/DiditWebhookSignature.php';
require $root.'/packages/KYCPlugin/Drivers/Didit/DiditOperatorNoticePrivacy.php';
require $root.'/app/Services/TelegramOperator/TelegramSecretRedactor.php';

use App\Services\TelegramOperator\TelegramSecretRedactor;
use iEXPackages\KYCPlugin\Drivers\Didit\DiditOperatorNoticePrivacy;
use iEXPackages\KYCPlugin\Drivers\Didit\DiditStatusNormalizer;
use iEXPackages\KYCPlugin\Drivers\Didit\DiditStatusTransition;
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

$check('normalize_not_started', DiditStatusNormalizer::normalize('Not Started') === 'not_started');
$check('normalize_in_progress_pending', DiditStatusNormalizer::normalize('In Progress') === 'pending');
$check('normalize_approved', DiditStatusNormalizer::normalize('Approved') === 'approved');
$check('normalize_declined_rejected', DiditStatusNormalizer::normalize('Declined') === 'rejected');
$check('normalize_expired', DiditStatusNormalizer::normalize('Expired') === 'expired');
$check('normalize_abandoned', DiditStatusNormalizer::normalize('Abandoned') === 'abandoned');
$check('may_set_verified_only_approved', DiditStatusNormalizer::maySetVerified('approved') && !DiditStatusNormalizer::maySetVerified('pending'));

$matrix = [
    ['not_started', 'pending', true],
    ['pending', 'approved', true],
    ['pending', 'rejected', true],
    ['pending', 'expired', true],
    ['approved', 'approved', true],
    ['approved', 'pending', false],
    ['approved', 'rejected', false],
    ['approved', 'expired', false],
    ['rejected', 'approved', true],
    ['expired', 'approved', true],
    ['abandoned', 'approved', true],
];
foreach ($matrix as [$from, $to, $allowed]) {
    $check(
        "transition_{$from}_to_{$to}_".($allowed ? 'allowed' : 'blocked'),
        DiditStatusTransition::mayOverwriteSession($from, $to) === $allowed
    );
}

$secret = 'test_webhook_secret_value_for_unit';
$body = [
    'webhook_type' => 'status.updated',
    'session_id' => 'sess_abc',
    'status' => 'Approved',
    'timestamp' => 1774970000,
    'decision' => ['score' => 1.0, 'nested' => ['z' => 1, 'a' => 2]],
    'vendor_data' => 'ref-1',
];
$raw = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
$v2 = hash_hmac('sha256', DiditWebhookSignature::canonicalJson($body), $secret);
$rawSig = hash_hmac('sha256', $raw, $secret);
$check('webhook_v2_valid', DiditWebhookSignature::isValid($secret, $raw, $body, $v2, null, null));
$check('webhook_v2_rejects_tamper', !DiditWebhookSignature::isValid($secret, $raw, $body, 'deadbeef', null, null));
$check('webhook_raw_valid', DiditWebhookSignature::isValid($secret, $raw, $body, null, $rawSig, null));
$check('webhook_wrong_secret_fails', !DiditWebhookSignature::isValid('other', $raw, $body, $v2, $rawSig, null));
$check('webhook_empty_secret_fails', !DiditWebhookSignature::isValid('', $raw, $body, $v2, $rawSig, null));

$check('mask_email_keeps_domain', DiditOperatorNoticePrivacy::maskEmail('alice@example.com') === 'ali***@example.com');
$check('mask_email_empty', DiditOperatorNoticePrivacy::maskEmail('') === '***');
$check('mask_session_short', DiditOperatorNoticePrivacy::maskSessionId('abcd') === '********');
$check('mask_session_long', DiditOperatorNoticePrivacy::maskSessionId('ABCDEFGHIJKL') === 'ABCD…IJKL');

$files = [
    'controller' => $root.'/packages/ExchangerClient/Http/Controller/Account/IdentityVerifyController.php',
    'routes' => $root.'/packages/ExchangerClient/routes/client-api.php',
    'didit_service' => $root.'/packages/KYCPlugin/Services/DiditKycService.php',
    'resolver' => $root.'/packages/KYCPlugin/Services/KycProviderResolver.php',
    'webhook' => $root.'/app/Http/Controllers/Callbacks/DiditWebhookController.php',
    'admin' => $root.'/app/Http/Controllers/Administrator/Verifications/VerificationAccountController.php',
    'job' => $root.'/app/Jobs/Telegram/TelegramIdentityVerifiedJob.php',
    'notice' => $root.'/app/Notifications/TelegramIdentityVerified.php',
    'transition' => $root.'/packages/KYCPlugin/Drivers/Didit/DiditStatusTransition.php',
    'redactor' => $root.'/app/Services/TelegramOperator/TelegramSecretRedactor.php',
];
foreach ($files as $label => $path) {
    $check("file_exists_{$label}", is_file($path));
}

$controller = (string) file_get_contents($files['controller']);
$routes = (string) file_get_contents($files['routes']);
$service = (string) file_get_contents($files['didit_service']);
$webhook = (string) file_get_contents($files['webhook']);
$admin = (string) file_get_contents($files['admin']);
$job = (string) file_get_contents($files['job']);
$notice = (string) file_get_contents($files['notice']);

$indexPos = strpos($controller, 'function index');
$startPos = strpos($controller, 'function start');
$storePos = strpos($controller, 'function store');
$check('controller_has_index_and_start', is_int($indexPos) && is_int($startPos) && $indexPos < $startPos);
$indexBody = is_int($indexPos) && is_int($startPos)
    ? substr($controller, $indexPos, $startPos - $indexPos)
    : '';
$startBody = is_int($startPos) && is_int($storePos)
    ? substr($controller, $startPos, $storePos - $startPos)
    : '';

$check('get_status_uses_readLocalStatus', str_contains($indexBody, 'readLocalStatus'));
$check('get_status_never_creates_didit_session', !str_contains($indexBody, 'createVerificationSession'));
$check('get_status_never_retrieves_remote', !str_contains($indexBody, 'retrieveVerificationStatus') && !str_contains($indexBody, 'DiditClient'));
$check('get_uses_provider_resolver', str_contains($indexBody, 'KycProviderResolver'));
$check('start_creates_or_reuses_session', str_contains($startBody, 'createVerificationSession'));
$check('start_blocks_non_didit', str_contains($startBody, 'PROVIDER_DIDIT'));
$check('store_rejects_didit_eligible', str_contains($controller, 'Didit start is required for this account'));
$check('route_registers_start_before_id', str_contains($routes, "post('/identity-verification/start'")
    && strpos($routes, "post('/identity-verification/start'") < strpos($routes, "get('/identity-verification/{id}'"));
$check('start_inside_auth_group', str_contains($routes, "middleware(['auth'])")
    && strpos($routes, "middleware(['auth'])") < strpos($routes, "post('/identity-verification/start'"));

$check('service_get_helper_is_read_local', str_contains($service, 'function readLocalStatus'));
$check('service_create_locks_user', str_contains($service, 'lockForUpdate()'));
$check('service_never_clears_is_verify', !preg_match('/is_verify_account\s*=\s*0/', $service));
$check('service_first_approval_enqueues_telegram', str_contains($service, 'TelegramIdentityVerifiedJob::dispatch')
    && str_contains($service, '$firstApproval'));
$check('service_blocks_approved_overwrite', str_contains($service, 'DiditStatusTransition::mayOverwriteSession'));
$check('service_telegram_failure_does_not_throw_out_of_apply', str_contains($service, 'telegram_identity_verified_enqueue_failed'));

$check('webhook_fail_closed_signature', str_contains($webhook, 'unauthorized') && str_contains($webhook, 'DiditWebhookSignature::isValid'));
$check('webhook_unknown_session_ok', str_contains($webhook, 'didit_webhook_unknown_session') && str_contains($webhook, "return response('ok', 200)"));
$check('webhook_duplicate_event', str_contains($webhook, 'didit_webhook_duplicate'));

$check('admin_preserves_didit_approval', str_contains($admin, 'hasAuthoritativeDiditApproval')
    && str_contains($admin, 'verification_account_admin_preserved_didit_approval'));
$check('admin_destroy_does_not_blindly_clear', str_contains($admin, 'verification_account_admin_delete_preserved_didit_approval'));

$check('telegram_job_unique_per_user', str_contains($job, "telegram-identity-verified:") && str_contains($job, 'ShouldBeUnique'));
$check('telegram_uses_private_operator_chats', str_contains($job, 'authorizedActionTelegramUserIds')
    && str_contains($job, 'telegram_identity_verified_sent:')
    && !str_contains($job, 'id_channel')
    && !str_contains($job, 'TelegramNotification'));
$check('telegram_notice_uses_operator_token_only', str_contains($notice, 'resolve(null)')
    && str_contains($notice, 'TelegramBotTokenResolver')
    && str_contains($notice, 'operatorChatId')
    && !str_contains($notice, 'token_access'));
$payment = (string) file_get_contents($root.'/app/Notifications/TelegramPaymentFailed.php');
$orderJob = (string) file_get_contents($root.'/app/Jobs/Telegram/TelegramOrderJob.php');
$check('channel_payment_notice_still_uses_row_token', str_contains($payment, 'token_access'));
$check('order_job_still_uses_channel_row', str_contains($orderJob, 'id_channel') && str_contains($orderJob, 'TelegramNotificationSelector'));
$check('telegram_job_redacts_secrets', str_contains($job, 'TelegramSecretRedactor::message'));
$leaky = 'Client error: POST https://api.telegram.org/bot123456:AAEXAMPLETOKENVALUEFORTESTXXXX/sendMessage chat not found';
$safe = TelegramSecretRedactor::message($leaky);
$check('telegram_redactor_strips_bot_url', !str_contains($safe, 'AAEXAMPLETOKENVALUEFORTESTXXXX')
    && str_contains($safe, 'bot[redacted]'));
$check('telegram_notice_no_raw_pii_fields', !str_contains($notice, 'passport')
    && !str_contains($notice, 'selfie')
    && !str_contains($notice, 'date_of_birth')
    && !str_contains($notice, 'webhook_secret'));
$check('telegram_notice_privacy_helper', str_contains($notice, 'DiditOperatorNoticePrivacy'));

echo "SUMMARY PASS={$pass} FAIL={$fail}\n";
exit($fail > 0 ? 1 : 0);
