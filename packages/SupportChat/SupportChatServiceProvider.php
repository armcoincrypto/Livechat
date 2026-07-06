<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat;

use iEXPackages\SupportChat\Contracts\SupportChatServiceInterface;
use iEXPackages\SupportChat\Services\SupportAttachmentStorageService;
use iEXPackages\SupportChat\Services\SupportChatAdminRetryService;
use iEXPackages\SupportChat\Services\SupportChatDeliveryDiagnostics;
use iEXPackages\SupportChat\Services\SupportChatHealthService;
use iEXPackages\SupportChat\Services\SupportChatSchemaReadinessService;
use iEXPackages\SupportChat\Services\SupportChatService;
use iEXPackages\SupportChat\Services\SupportConversationLifecycleService;
use iEXPackages\SupportChat\Services\SupportTelegramAttachmentOutboundService;
use iEXPackages\SupportChat\Services\SupportTelegramDeliveryStatusService;
use iEXPackages\SupportChat\Services\SupportTelegramForumTopicService;
use iEXPackages\SupportChat\Services\SupportTelegramInboundMediaService;
use iEXPackages\SupportChat\Services\SupportTelegramInboundService;
use iEXPackages\SupportChat\Services\SupportTelegramOperatorCommandService;
use iEXPackages\SupportChat\Services\SupportTelegramOutboundService;
use iEXPackages\SupportChat\Services\SupportVisitorContextService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class SupportChatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SupportChatServiceInterface::class, SupportChatService::class);
        $this->app->singleton(SupportConversationLifecycleService::class);
        $this->app->singleton(SupportTelegramForumTopicService::class);
        $this->app->singleton(SupportTelegramOperatorCommandService::class);
        $this->app->singleton(SupportTelegramOutboundService::class);
        $this->app->singleton(SupportTelegramInboundMediaService::class);
        $this->app->singleton(SupportTelegramInboundService::class);
        $this->app->singleton(SupportTelegramAttachmentOutboundService::class);
        $this->app->singleton(SupportAttachmentStorageService::class);
        $this->app->singleton(SupportChatDeliveryDiagnostics::class);
        $this->app->singleton(SupportChatSchemaReadinessService::class);
        $this->app->singleton(SupportTelegramDeliveryStatusService::class);
        $this->app->singleton(SupportVisitorContextService::class);
        $this->app->singleton(SupportChatHealthService::class);
        $this->app->singleton(SupportChatAdminRetryService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(base_path('database/migrations/11.0.5'));
        $this->configureRateLimiting();
        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes/support-public-api.php');
        $this->loadRoutesFrom(__DIR__.'/routes/support-telegram-webhook.php');
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('support-create', function (Request $request) {
            $perHour = (int) config('support_chat.rate_limits.create.per_hour', 20);
            $perMinute = (int) config('support_chat.rate_limits.create.per_minute', 5);

            return [
                Limit::perHour($perHour)->by('support-create-hour:'.$request->ip()),
                Limit::perMinute($perMinute)->by('support-create-minute:'.$request->ip()),
            ];
        });

        RateLimiter::for('support-send', function (Request $request) {
            $perMinute = (int) config('support_chat.rate_limits.send.per_minute', 30);
            $token = $request->bearerToken() ?? '';
            $key = $token !== '' ? hash('sha256', $token) : 'ip:'.$request->ip();

            return Limit::perMinute($perMinute)->by('support-send:'.$key);
        });

        RateLimiter::for('support-poll', function (Request $request) {
            $perMinute = (int) config('support_chat.rate_limits.poll.per_minute', 120);
            $token = $request->bearerToken() ?? '';
            $key = $token !== '' ? hash('sha256', $token) : 'ip:'.$request->ip();

            return Limit::perMinute($perMinute)->by('support-poll:'.$key);
        });
    }
}
