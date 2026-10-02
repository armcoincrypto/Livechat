<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Jobs\Telegram\TelegramAutoPaymentJob;
use App\Jobs\Telegram\TelegramOrderJob;
use App\Models\Task;
use App\Services\TelegramOperator\TelegramProviderErrorClassifier;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
use iEXPackages\SmartMailer\DispatchJobs\Orders\OrderCompletedMailJob;
use iEXPackages\SmartMailer\SmartQueueableJob;
use ReflectionClass;
use Tests\TestCase;

final class NotificationJobsReliabilityTest extends TestCase
{
    public function test_telegram_order_job_deletes_when_missing_models(): void
    {
        $ref = new ReflectionClass(TelegramOrderJob::class);
        $this->assertTrue($ref->getProperty('deleteWhenMissingModels')->getDefaultValue()
            || (new TelegramOrderJob(new Task(), 'lifecycle_paid'))->deleteWhenMissingModels === true);
        $job = (new ReflectionClass(TelegramOrderJob::class))->newInstanceWithoutConstructor();
        // Instantiate with a transient Task model (not persisted) is fine for property check via trait default.
        $task = new Task();
        $task->id = 1;
        $j = new TelegramOrderJob($task, 'lifecycle_paid');
        $this->assertTrue($j->deleteWhenMissingModels);
        $this->assertSame(3, $j->tries);
        $this->assertSame([10, 30, 90], $j->backoff);
        $this->assertSame(20, $j->timeout);
    }

    public function test_classifier_permanent_vs_retryable(): void
    {
        $this->assertTrue(TelegramProviderErrorClassifier::isPermanent(new ModelNotFoundException()));
        $this->assertFalse(TelegramProviderErrorClassifier::isRetryable(new ModelNotFoundException()));

        $req = new Request('POST', 'https://api.telegram.org/botX/sendMessage');
        $bad = new ClientException('bad', $req, new Response(400));
        $this->assertTrue(TelegramProviderErrorClassifier::isPermanent($bad));
        $this->assertFalse(TelegramProviderErrorClassifier::isRetryable($bad));

        $unauth = new ClientException('unauth', $req, new Response(401));
        $this->assertTrue(TelegramProviderErrorClassifier::isPermanent($unauth));

        $forbidden = new ClientException('forbid', $req, new Response(403));
        $this->assertTrue(TelegramProviderErrorClassifier::isPermanent($forbidden));

        $rate = new ClientException('rate', $req, new Response(429));
        $this->assertFalse(TelegramProviderErrorClassifier::isPermanent($rate));
        $this->assertTrue(TelegramProviderErrorClassifier::isRetryable($rate));

        $server = new ServerException('boom', $req, new Response(502));
        $this->assertTrue(TelegramProviderErrorClassifier::isRetryable($server));

        $conn = new ConnectException('down', $req);
        $this->assertTrue(TelegramProviderErrorClassifier::isRetryable($conn));
    }

    public function test_auto_payment_job_has_unique_and_policy(): void
    {
        $task = new Task();
        $task->id = 42;
        $job = new TelegramAutoPaymentJob($task, 'kobbopay');
        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 30, 90], $job->backoff);
        $this->assertTrue($job->deleteWhenMissingModels);
        $this->assertSame('telegram-auto-payment:42:kobbopay', $job->uniqueId());
    }

    public function test_smart_queueable_default_policy(): void
    {
        $job = new class extends SmartQueueableJob {
            protected function run(): void {}
        };
        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 30, 90], $job->backoff);
        $this->assertSame(60, $job->timeout);
        $this->assertTrue($job->deleteWhenMissingModels);
    }

    public function test_order_completed_mail_idempotent_cache_sentinel(): void
    {
        Cache::flush();
        $task = new Task();
        $task->id = 777001;
        $task->email = 'noreply-test@example.invalid';

        Cache::put('smartmail_sent:order_completed:777001', 1, now()->addHour());

        $job = new OrderCompletedMailJob($task);
        // Should no-op without sending mail (no exception).
        $ref = new ReflectionClass($job);
        $m = $ref->getMethod('run');
        $m->setAccessible(true);
        $m->invoke($job);
        $this->assertTrue(Cache::has('smartmail_sent:order_completed:777001'));
    }

    public function test_high_risk_telegram_jobs_source_contract(): void
    {
        $files = [
            'TelegramOrderJob.php',
            'TelegramAutoPaymentJob.php',
            'TelegramAutoPaymentFailed.php',
            'TelegramConfirmBlockchainJob.php',
            'TelegramPayoutsJob.php',
            'TelegramVerificationCardJob.php',
            'TelegramOrderPostponedJob.php',
            'TelegramIdentityVerifiedJob.php',
        ];
        foreach ($files as $file) {
            $src = (string) file_get_contents(dirname(__DIR__, 3).'/app/Jobs/Telegram/'.$file);
            $this->assertStringContainsString('TelegramNotificationReliability', $src, $file);
            if ($file !== 'TelegramOrderJob.php' && $file !== 'TelegramIdentityVerifiedJob.php') {
                // Order/Identity already had policies; others must not use nested notify() hop.
                if (str_contains($src, 'notify(') && ! str_contains($src, 'notifyNow(')) {
                    $this->fail("$file still uses notify() without notifyNow()");
                }
            }
        }
        // Trait defines deleteWhenMissingModels
        $trait = (string) file_get_contents(dirname(__DIR__, 3).'/app/Jobs/Telegram/Concerns/TelegramNotificationReliability.php');
        $this->assertStringContainsString('deleteWhenMissingModels = true', $trait);
    }

    public function test_paid_after_claim_dedupe_keys_still_present_in_lifecycle_publisher(): void
    {
        $publisher = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/TelegramOperator/TelegramOrderLifecyclePublisher.php');
        $registry = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/TelegramOperator/TelegramOrderCardRegistry.php');
        $this->assertStringContainsString('paid_attention_alert_sent', $registry);
        $this->assertStringContainsString('telegram_order_paid_alert_lock', $publisher);
        $this->assertStringContainsString('telegram_order_card_lock', $publisher);
        $this->assertStringContainsString('skipped_stale', $publisher);
        $this->assertStringContainsString('sendPaidAttentionAlertOnce', $publisher);
    }
}
