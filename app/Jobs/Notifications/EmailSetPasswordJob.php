<?php

declare(strict_types=1);

namespace App\Jobs\Notifications;

use App\Models\User;
use App\Notifications\SetPasswordNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queue first-time set-password invite after order auto-registration.
 * Order creation must not depend on SMTP success.
 */
final class EmailSetPasswordJob implements ShouldQueue
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
            $this->user->notify(new SetPasswordNotification($this->token));
        } catch (Throwable $e) {
            Log::warning('EmailSetPasswordJob mail failed', ['error' => $e->getMessage()]);
        }
    }
}
