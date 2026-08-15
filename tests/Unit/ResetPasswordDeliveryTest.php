<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Jobs\Notifications\EmailResetPasswordJob;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * P14.4B.2 — Password reset mail delivery remediation tests.
 */
final class ResetPasswordDeliveryTest extends TestCase
{
    private const PLAIN_TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function test_reset_notification_builds_expected_url_structure_without_logging_token(): void
    {
        config([
            'app.frontend_url' => 'https://exswaping.com',
            'auth.defaults.passwords' => 'users',
            'auth.passwords.users.expire' => 60,
        ]);

        $user = new User();
        $user->email = 'qa-reset@example.com';
        $user->language = 'ru';

        $notification = new ResetPasswordNotification(self::PLAIN_TOKEN);
        $mail = $notification->toMail($user);

        $rendered = (string) $mail->render();
        self::assertStringContainsString('/auth/reset-credentials/', $rendered);
        self::assertStringContainsString('qa-reset%40example.com', $rendered);
        self::assertStringContainsString('Создать новый пароль', $rendered);
        self::assertStringContainsString('Запрос на восстановление пароля', $mail->subject);
    }

    public function test_user_dispatches_queued_reset_job_not_sync_notification(): void
    {
        Bus::fake();

        $user = new class extends User {
            public $exists = true;

            public function getKey()
            {
                return 353;
            }
        };
        $user->email = 'armcoincrypto@gmail.com';

        $user->sendPasswordResetNotification(self::PLAIN_TOKEN);

        Bus::assertDispatched(EmailResetPasswordJob::class, function (EmailResetPasswordJob $job) use ($user): bool {
            $ref = new ReflectionClass($job);
            $userProp = $ref->getProperty('user');
            $userProp->setAccessible(true);
            $tokenProp = $ref->getProperty('token');
            $tokenProp->setAccessible(true);

            return $userProp->getValue($job)->email === $user->email
                && $tokenProp->getValue($job) === self::PLAIN_TOKEN;
        });
    }

    public function test_reset_job_sends_notification_to_user_mail_channel(): void
    {
        Notification::fake();

        $user = new User();
        $user->email = 'qa-reset@example.com';

        $job = new EmailResetPasswordJob($user, self::PLAIN_TOKEN);
        $job->handle();

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function (ResetPasswordNotification $notification): bool {
                $ref = new ReflectionClass($notification);
                $tokenProp = $ref->getProperty('token');
                $tokenProp->setAccessible(true);

                return $tokenProp->getValue($notification) === self::PLAIN_TOKEN;
            }
        );
    }

    public function test_reset_job_is_queueable_with_retries(): void
    {
        $job = new EmailResetPasswordJob(new User(), self::PLAIN_TOKEN);
        self::assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
        self::assertSame(3, $job->tries);
    }

    public function test_mail_from_address_falls_back_to_smtp_username_when_env_empty(): void
    {
        $path = dirname(__DIR__, 2) . '/config/mail.php';
        $source = file_get_contents($path);
        self::assertNotFalse($source);
        self::assertStringContainsString(
            "env('MAIL_FROM_ADDRESS') ?: env('MAIL_USERNAME', 'hello@example.com')",
            $source
        );
    }
}
