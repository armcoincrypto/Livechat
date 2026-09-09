<?php

declare(strict_types=1);

namespace App\Jobs\Notifications;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queue password-reset mail so delivery is retryable and visible in worker logs.
 */
final class EmailResetPasswordJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        protected User $user,
        #[\SensitiveParameter] protected string $token,
    ) {
    }

    public function handle(): void
    {
        try {
            $this->user->notify(new ResetPasswordNotification($this->token));
        } catch (Throwable $e) {
            Log::warning('EmailResetPasswordJob mail failed', ['error' => $e->getMessage()]);
        }
    }
}
