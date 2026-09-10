<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Jobs\Notifications\EmailSetPasswordJob;
use App\Models\User;
use App\Notifications\SetPasswordNotification;
use ArrayObject;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use Throwable;

/**
 * Queue reliability for first-time set-password mail.
 * Token/URL semantics stay on SetPasswordNotification; this job must not swallow SMTP failures.
 */
final class EmailSetPasswordJobReliabilityTest extends TestCase
{
    private const PLAIN_TOKEN = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function test_successful_notification_completes_once_without_real_mailer(): void
    {
        Notification::fake();
        $jobLogs = $this->captureJobLogs();

        $user = new User();
        $user->email = 'qa-set-password@example.com';
        $user->id = 911;

        $job = new EmailSetPasswordJob($user, self::PLAIN_TOKEN);
        $job->handle();

        Notification::assertSentTo($user, SetPasswordNotification::class);
        Notification::assertCount(1);
        self::assertCount(0, $jobLogs);
    }

    public function test_smtp_exception_is_logged_and_rethrown_so_queue_retries_apply(): void
    {
        $jobLogs = $this->captureJobLogs();

        $user = new class extends User {
            public function notify($instance): void
            {
                throw new RuntimeException('simulated smtp 421');
            }
        };
        $user->id = 912;
        $user->email = 'qa-set-password@example.com';

        $job = new EmailSetPasswordJob($user, self::PLAIN_TOKEN);

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
        self::assertSame('EmailSetPasswordJob mail failed', $event->message);
        self::assertSame('simulated smtp 421', $event->context['error'] ?? null);
        self::assertSame(RuntimeException::class, $event->context['exception'] ?? null);
        self::assertSame($user->getKey(), $event->context['user_id'] ?? null);
        $encoded = json_encode($event->context);
        self::assertIsString($encoded);
        self::assertStringNotContainsString(self::PLAIN_TOKEN, $encoded);
        self::assertStringNotContainsString('qa-set-password@example.com', $encoded);
    }

    public function test_transient_smtp_failure_then_success_retries_same_token(): void
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

        $job = new EmailSetPasswordJob($user, self::PLAIN_TOKEN);

        try {
            $job->handle();
            self::fail('first attempt must throw so Laravel can retry');
        } catch (RuntimeException $e) {
            self::assertSame('transient smtp 421', $e->getMessage());
        }

        $job->handle();
        self::assertSame(2, $user->notifyCalls);

        $ref = new ReflectionClass($job);
        $tokenProp = $ref->getProperty('token');
        $tokenProp->setAccessible(true);
        self::assertSame(self::PLAIN_TOKEN, $tokenProp->getValue($job));
    }

    public function test_job_is_queueable_with_retries_and_no_failed_handler(): void
    {
        $job = new EmailSetPasswordJob(new User(), self::PLAIN_TOKEN);
        self::assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
        self::assertSame(3, $job->tries);
        self::assertSame([10, 30, 90], $job->backoff);
        $ref = new ReflectionClass(EmailSetPasswordJob::class);
        self::assertFalse($ref->hasMethod('failed'), 'failed() would hide exhausted SMTP attempts');
    }

    public function test_set_password_url_semantics_unchanged(): void
    {
        config(['app.frontend_url' => 'https://exswaping.com']);

        $user = new User();
        $user->email = 'lifecycle-test@example.com';
        $user->language = 'en';

        $mail = (new SetPasswordNotification(self::PLAIN_TOKEN))->toMail($user);

        self::assertSame(
            'https://exswaping.com/auth/reset-credentials/' . self::PLAIN_TOKEN . '/?email=lifecycle-test%40example.com',
            $mail->actionUrl,
        );
    }

    /**
     * @return ArrayObject<int, MessageLogged>
     */
    private function captureJobLogs(): ArrayObject
    {
        $jobLogs = new ArrayObject();
        Log::listen(function (MessageLogged $event) use ($jobLogs): void {
            if (str_contains((string) $event->message, 'EmailSetPasswordJob')) {
                $jobLogs[] = $event;
            }
        });

        return $jobLogs;
    }
}
