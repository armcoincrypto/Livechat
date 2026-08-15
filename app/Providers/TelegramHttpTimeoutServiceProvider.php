<?php

declare(strict_types=1);

namespace App\Providers;

use GuzzleHttp\Client as HttpClient;
use Illuminate\Support\ServiceProvider;
use NotificationChannels\Telegram\Telegram;

/**
 * Bound connect/total timeouts for operator Telegram HTTP.
 * Does not change the shared Guzzle singleton used elsewhere.
 */
final class TelegramHttpTimeoutServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Telegram::class, function () {
            $connect = (float) config('services.telegram-bot-api.connect_timeout', 2);
            $timeout = (float) config('services.telegram-bot-api.timeout', 5);

            return new Telegram(
                config('services.telegram-bot-api.token'),
                new HttpClient([
                    'connect_timeout' => $connect,
                    'timeout' => $timeout,
                ]),
                config('services.telegram-bot-api.base_uri')
            );
        });
    }
}
