<?php

declare(strict_types=1);

namespace App\Jobs\Telegram\Concerns;

use App\Services\TelegramOperator\TelegramProviderErrorClassifier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Shared bounded retry + send-sentinel helpers for Telegram Jobs.
 * Prefer notifyNow() so retry ownership stays on this job (no nested ShouldQueue hop).
 */
trait TelegramNotificationReliability
{
    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30, 90];

    public int $timeout = 20;

    /**
     * Avoid ModelNotFoundException retry storms when SerializesModels target is gone.
     */
    public bool $deleteWhenMissingModels = true;

    protected function telegramSentKey(string $scope, string|int $id, string $event): string
    {
        return 'telegram_notify_sent:'.$scope.':'.$id.':'.$event;
    }

    protected function telegramAlreadySent(string $key): bool
    {
        return (bool) Cache::get($key);
    }

    protected function telegramMarkSent(string $key, int $days = 7): void
    {
        Cache::put($key, 1, now()->addDays($days));
    }

    protected function telegramFailPermanentOrRetry(Throwable $e, array $context = []): void
    {
        if (TelegramProviderErrorClassifier::isPermanent($e)) {
            Log::warning('telegram_notify_permanent_failure', array_merge($context, [
                'job' => static::class,
                'exception_class' => $e::class,
                'http_status' => TelegramProviderErrorClassifier::httpStatus($e),
                'attempt' => method_exists($this, 'attempts') ? $this->attempts() : null,
                'disposition' => 'fail_no_retry',
            ]));
            $this->fail($e);

            return;
        }

        throw $e;
    }

    protected function telegramLogDead(?Throwable $exception, array $context = []): void
    {
        Log::error('telegram_notify_dead', array_merge($context, [
            'job' => static::class,
            'exception_class' => $exception ? $exception::class : null,
            'http_status' => $exception ? TelegramProviderErrorClassifier::httpStatus($exception) : null,
            'attempt' => method_exists($this, 'attempts') ? $this->attempts() : null,
            'disposition' => 'exhausted',
        ]));
    }
}
