<?php

namespace App\Jobs;

use App\Mail\Auth\LockoutAuthMail;
use App\Models\User;
use iEXPackages\GeoIp\Facades\GeoIP;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UserLockedOutJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Детали пользователя
     */
    protected User $user;

    /**
     * Дополнительная информация
     */
    protected string $ip;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, string $ip)
    {
        $this->user = $user;
        $this->ip = $ip;
    }

    /**
     * Execute the job.
     *
     * @throws \Exception
     */
    public function handle(): void
    {
        $data = [
            'ip' => $this->ip,
            'geo' => null,
        ];

        if (
            (bool) iEXSetting('geoip.toggles.enabled', true)
            && !empty($this->ip)
        ) {
            $loc = GeoIP::locate($this->ip);
            $data['geo'] = $loc->summaryExtended();
        }


        \Mail::to($this->user->email)
            ->send(new LockoutAuthMail($this->user, $data));
    }
}
