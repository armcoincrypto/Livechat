<?php

namespace App\Jobs\Notifications;

use App\Notifications\LoginSuccessfulNotification;
use iEXPackages\GeoIp\Facades\GeoIP;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EmailLoginSuccessfulJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Данные пользователя
     */
    protected Authenticatable $user;

    /**
     * Create a new job instance.
     */
    public function __construct(Authenticatable $user)
    {
        $this->user = $user;
    }

    /**
     * Execute the job.
     *
     * @throws \Exception
     */
    public function handle(): void
    {
        // Определение местоположения
        $geo_string = null;

        if ((bool) iEXSetting('geoip.toggles.enabled', true) && !empty($this->user->ip_address)) {
            $loc = GeoIP::locate($this->user->ip_address);
            $geo_string = $loc->summaryExtended();
        }

        // Here the email verification will be sent to the user
        $this->user->notify(new LoginSuccessfulNotification($geo_string));
    }
}
