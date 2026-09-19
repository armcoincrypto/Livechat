<?php

declare(strict_types=1);

namespace Tests\Unit\Kyc;

use Tests\TestCase;

/**
 * File-content contracts for Didit customer/admin/webhook wiring.
 * These tests do not mutate a database.
 */
final class DiditRemediationContractTest extends TestCase
{
    public function test_get_status_is_read_only(): void
    {
        $src = (string) file_get_contents(base_path('packages/ExchangerClient/Http/Controller/Account/IdentityVerifyController.php'));
        $indexPos = strpos($src, 'function index');
        $startPos = strpos($src, 'function start');
        $this->assertNotFalse($indexPos);
        $this->assertNotFalse($startPos);
        $index = substr($src, $indexPos, $startPos - $indexPos);
        $this->assertStringContainsString('readLocalStatus', $index);
        $this->assertStringNotContainsString('createVerificationSession', $index);
        $this->assertStringNotContainsString('retrieveVerificationStatus', $index);
        $this->assertStringNotContainsString('DiditClient', $index);
    }

    public function test_explicit_start_route_is_registered_inside_auth(): void
    {
        $src = (string) file_get_contents(base_path('packages/ExchangerClient/routes/client-api.php'));
        $this->assertStringContainsString("post('/identity-verification/start'", $src);
        $this->assertLessThan(
            strpos($src, "get('/identity-verification/{id}'") ?: PHP_INT_MAX,
            strpos($src, "post('/identity-verification/start'") ?: 0
        );
    }

    public function test_webhook_and_admin_and_telegram_guards(): void
    {
        $service = (string) file_get_contents(base_path('packages/KYCPlugin/Services/DiditKycService.php'));
        $this->assertStringContainsString('DiditStatusTransition::mayOverwriteSession', $service);
        $this->assertStringContainsString('TelegramIdentityVerifiedJob::dispatch', $service);
        $this->assertDoesNotMatchRegularExpression('/is_verify_account\s*=\s*0/', $service);

        $admin = (string) file_get_contents(base_path('app/Http/Controllers/Administrator/Verifications/VerificationAccountController.php'));
        $this->assertStringContainsString('hasAuthoritativeDiditApproval', $admin);

        $job = (string) file_get_contents(base_path('app/Jobs/Telegram/TelegramIdentityVerifiedJob.php'));
        $this->assertStringContainsString('telegram-identity-verified:', $job);
        $this->assertStringContainsString('ShouldBeUnique', $job);
        $this->assertStringContainsString('TelegramSecretRedactor::message', $job);
        $this->assertStringContainsString('authorizedActionTelegramUserIds', $job);
        $this->assertStringContainsString('telegram_identity_verified_sent:', $job);
        $this->assertStringNotContainsString('id_channel', $job);
        $this->assertStringNotContainsString('TelegramNotification', $job);

        $notice = (string) file_get_contents(base_path('app/Notifications/TelegramIdentityVerified.php'));
        $this->assertStringContainsString('resolve(null)', $notice);
        $this->assertStringContainsString('TelegramBotTokenResolver', $notice);
        $this->assertStringContainsString('operatorChatId', $notice);
        $this->assertStringNotContainsString('token_access', $notice);
        $this->assertStringNotContainsString('id_channel', $notice);

        $channel = (string) file_get_contents(base_path('app/Notifications/TelegramPaymentFailed.php'));
        $this->assertStringContainsString('token_access', $channel);
        $orders = (string) file_get_contents(base_path('app/Jobs/Telegram/TelegramOrderJob.php'));
        $this->assertStringContainsString('id_channel', $orders);
    }
}
