<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Jobs\Notifications\EmailResetPasswordJob;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use ArrayObject;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use ReflectionClass;
use RuntimeException;
use Throwable;

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
        self::assertSame([10, 30, 90], $job->backoff);
    }

    public function test_smtp_success_completes_job_once_without_real_mailer(): void
    {
        Notification::fake();
        $jobLogs = $this->captureResetJobLogs();

        $user = new User();
        $user->email = 'qa-reset@example.com';
        $user->id = 901;

        $job = new EmailResetPasswordJob($user, self::PLAIN_TOKEN);
        $job->handle();

        Notification::assertSentTo($user, ResetPasswordNotification::class);
        Notification::assertCount(1);
        self::assertCount(0, $jobLogs);
    }

    public function test_smtp_exception_is_logged_and_rethrown_so_queue_retries_apply(): void
    {
        $jobLogs = $this->captureResetJobLogs();

        $user = new class extends User {
            public function notify($instance): void
            {
                throw new RuntimeException('simulated smtp 421');
            }
        };
        $user->id = 902;
        $user->email = 'qa-reset@example.com';

        $job = new EmailResetPasswordJob($user, self::PLAIN_TOKEN);

        $caught = null;
        try {
            $job->handle();
        } catch (Throwable $e) {
            $caught = $e;
        }

        self::assertInstanceOf(RuntimeException::class, $caught);
        self::assertSame('simulated smtp 421', $caught->getMessage());
        self::assertSame(3, $job->tries, 'swallowed exceptions would make $tries ineffective');
        self::assertCount(1, $jobLogs);

        $event = $jobLogs[0];
        self::assertSame('warning', $event->level);
        self::assertSame('EmailResetPasswordJob mail failed', $event->message);
        self::assertSame('simulated smtp 421', $event->context['error'] ?? null);
        self::assertSame(RuntimeException::class, $event->context['exception'] ?? null);
        self::assertSame($user->getKey(), $event->context['user_id'] ?? null);
        $encoded = json_encode($event->context);
        self::assertIsString($encoded);
        self::assertStringNotContainsString(self::PLAIN_TOKEN, $encoded);
        self::assertStringNotContainsString('qa-reset@example.com', $encoded);
    }

    public function test_transient_smtp_failure_then_success_can_retry_same_job(): void
    {
        $user = new class extends User {
            public int $notifyCalls = 0;

            public function notify($instance): void
            {
                $this->notifyCalls++;
                if ($this->notifyCalls === 1) {
                    throw new RuntimeException('transient smtp 421');
                }
            }
        };

        $job = new EmailResetPasswordJob($user, self::PLAIN_TOKEN);

        try {
            $job->handle();
            self::fail('first attempt must throw so Laravel can retry');
        } catch (RuntimeException $e) {
            self::assertSame('transient smtp 421', $e->getMessage());
        }

        $job->handle();
        self::assertSame(2, $user->notifyCalls);
    }

    public function test_job_has_no_failed_handler_that_would_hide_exhausted_attempts(): void
    {
        $ref = new ReflectionClass(EmailResetPasswordJob::class);
        self::assertFalse($ref->hasMethod('failed'), 'failed() would hide exhausted SMTP attempts');
    }

    /**
     * @return ArrayObject<int, MessageLogged>
     */
    private function captureResetJobLogs(): ArrayObject
    {
        $jobLogs = new ArrayObject();
        Log::listen(function (MessageLogged $event) use ($jobLogs): void {
            if (str_contains((string) $event->message, 'EmailResetPasswordJob')) {
                $jobLogs[] = $event;
            }
        });

        return $jobLogs;
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
