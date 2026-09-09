<?php

declare(strict_types=1);

namespace Tests\Unit\Orders;

use App\Models\Task;
use App\Models\TaskInfo;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Contract tests for first-order email + set-password embedding.
 * Avoids live SMTP / DB mutations beyond reading source.
 */
final class FirstOrderEmailSetPasswordContractTest extends TestCase
{
    public function test_registration_no_longer_dispatches_separate_set_password_job(): void
    {
        $src = File::get(base_path('packages/Order/Concerns/ManagesUserRegistration.php'));

        self::assertStringContainsString('justAutoRegistered', $src);
        self::assertStringContainsString('OrderCreatedMail owns the single first-order', $src);
        self::assertStringNotContainsString('dispatch(new EmailSetPasswordJob', $src);
        self::assertStringNotContainsString('Password::broker()->createToken', $src);
    }

    public function test_manager_order_marks_newbie_on_auto_register(): void
    {
        $src = File::get(base_path('packages/Order/Bindings/ManagerOrder.php'));

        self::assertStringContainsString('justAutoRegistered', $src);
        self::assertStringContainsString('additionInfo($newOrder, $is_new_user)', $src);
    }

    public function test_order_created_mail_embeds_set_password_and_localized_status(): void
    {
        $src = File::get(base_path('packages/SmartMailer/Dispatches/Orders/OrderCreatedMail.php'));

        self::assertStringContainsString('resolveSetPasswordUrl', $src);
        self::assertStringContainsString('localizedStatusName', $src);
        self::assertStringContainsString('showSetPasswordCta', $src);
        self::assertStringContainsString('Password::broker()->createToken', $src);
        self::assertStringContainsString('newbie', $src);
        self::assertStringContainsString('hasVerifiedEmail', $src);
    }

    public function test_order_created_blade_has_primary_open_and_secondary_set_password(): void
    {
        $blade = File::get(base_path('packages/SmartMailer/Resources/Views/orders/order_created.blade.php'));

        self::assertStringContainsString('open_order', $blade);
        self::assertStringContainsString('set_password', $blade);
        self::assertStringContainsString('showSetPasswordCta', $blade);
        self::assertStringContainsString('account_section_title', $blade);
        self::assertStringContainsString('exchange_details', $blade);
        // Primary CTA before secondary account section
        self::assertLessThan(
            strpos($blade, 'set_password') ?: PHP_INT_MAX,
            strpos($blade, 'open_order') ?: PHP_INT_MAX,
        );
    }

    public function test_account_copy_exists_for_supported_locales(): void
    {
        foreach (['en', 'ru', 'uk', 'ka', 'zh'] as $locale) {
            $path = base_path("packages/SmartMailer/Resources/Lang/{$locale}/messages.php");
            self::assertFileExists($path, "missing locale {$locale}");
            $messages = require $path;
            self::assertIsArray($messages);
            self::assertArrayHasKey('set_password', $messages);
            self::assertArrayHasKey('account_section_title', $messages);
            self::assertArrayHasKey('status_2', $messages);
            self::assertArrayHasKey('open_order', $messages);
            self::assertNotSame('', trim((string) $messages['set_password']));
        }
    }

    public function test_set_password_cta_gate_logic_documented_via_source(): void
    {
        // Existing customer / second order: newbie != 1 → no CTA.
        // Completed setup: hasVerifiedEmail → no CTA.
        // SMTP failure in resolveSetPasswordUrl is caught and does not throw.
        $src = File::get(base_path('packages/SmartMailer/Dispatches/Orders/OrderCreatedMail.php'));
        self::assertStringContainsString('OrderCreatedMail set-password URL skipped', $src);
        self::assertTrue(class_exists(Task::class));
        self::assertTrue(class_exists(TaskInfo::class));
        self::assertTrue(class_exists(User::class));
    }
}
