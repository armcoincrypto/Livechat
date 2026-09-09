<?php

namespace App\Jobs\User;

use App\Models\User;
use App\Notifications\RegisterUserNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RegisterClientJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Детали клиента
     */
    protected User $user;

    /**
     * Новый пароль клиента
     */
    protected string $password;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, string $password)
    {
        $this->user = $user;
        $this->password = $password;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $this->user->notify(new RegisterUserNotification($this->password, $this->user?->language));
        } catch (Throwable $e) {
            Log::warning('RegisterClientJob mail failed', ['error' => $e->getMessage()]);
        }
    }
}
