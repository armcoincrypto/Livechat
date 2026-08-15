<?php
declare(strict_types=1);

namespace App\Jobs;

use App\Notifications\NewDeviceNotification;
use iEXPackages\AuthAudit\Models\AuthEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class NewDeviceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Authenticatable $user;

    private AuthEvent $authContext;

    public function __construct(Authenticatable $user, AuthEvent $authContext)
    {
        $this->user = $user;
        $this->authContext = $authContext;
    }

    public function handle(): void
    {
        $this->user->notify(new NewDeviceNotification($this->authContext));
    }
}
