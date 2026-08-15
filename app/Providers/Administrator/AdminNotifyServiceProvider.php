<?php

namespace App\Providers\Administrator;

use App\Services\Administrator\Notify\AdminNotifyCountsService;
use App\Services\Administrator\Notify\Contracts\AdminNotifyCounter;
use App\Services\Administrator\Notify\Counters\NotificationEventsCounter;
use App\Services\Administrator\Notify\Counters\OrderChatCounter;
use App\Services\Administrator\Notify\Counters\OrdersCounter;
use App\Services\Administrator\Notify\Counters\ReviewsCounter;
use App\Services\Administrator\Notify\Counters\VerificationAccountCounter;
use App\Services\Administrator\Notify\Counters\VerificationCardCounter;
use App\Services\Administrator\Notify\Counters\WithdrawalRequestsCounter;
use Illuminate\Support\ServiceProvider;

final class AdminNotifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AdminNotifyCountsService::class, function ($app) {
            /** @var array<int,AdminNotifyCounter> $counters */
            $counters = [
                $app->make(OrdersCounter::class),
                $app->make(OrderChatCounter::class),
                $app->make(NotificationEventsCounter::class),
                $app->make(ReviewsCounter::class),

                $app->make(WithdrawalRequestsCounter::class),
                $app->make(VerificationCardCounter::class),
                $app->make(VerificationAccountCounter::class),
            ];

            return new AdminNotifyCountsService($counters);
        });
    }
}
