<?php

declare(strict_types=1);

namespace iEXPackages\OrderChat;

use iEXPackages\OrderChat\Contracts\OrderChatServiceInterface;
use iEXPackages\OrderChat\Services\OrderChatService;
use Illuminate\Support\ServiceProvider;

final class OrderChatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OrderChatServiceInterface::class, OrderChatService::class);
    }

    public function boot(): void
    {
        //
    }
}
