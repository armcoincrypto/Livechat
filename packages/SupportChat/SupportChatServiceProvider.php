<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat;

use iEXPackages\SupportChat\Console\SupportChatAiDraftCommand;
use iEXPackages\SupportChat\Console\SupportAiUsageReportCommand;
use iEXPackages\SupportChat\Console\SupportChatAiLearningAcceptanceReportCommand;
use iEXPackages\SupportChat\Console\SupportChatAiLearningAnalyzeCommand;
use iEXPackages\SupportChat\Console\SupportChatAiLearningCandidateReadinessReportCommand;
use iEXPackages\SupportChat\Console\SupportChatAiLearningWeeklyAuditCommand;
use iEXPackages\SupportChat\Console\SupportChatAiLearningEvaluateCandidatesCommand;
use iEXPackages\SupportChat\Console\SupportChatAiLearningFilterCandidatesCommand;
use iEXPackages\SupportChat\Console\SupportChatAiLearningGenerateCandidatesCommand;
use iEXPackages\SupportChat\Console\SupportChatAiLearningOverlayPreviewCommand;
use iEXPackages\SupportChat\Console\SupportChatAiLearningMatchingDiagnosticsCommand;
use iEXPackages\SupportChat\Console\SupportChatAiLearningOutcomeReportCommand;
use iEXPackages\SupportChat\Console\SupportChatAiLearningPreviewGenerateCommand;
use iEXPackages\SupportChat\Console\SupportChatAiLearningPreviewReportCommand;
use iEXPackages\SupportChat\Console\SupportChatAiLearningPromotionReportCommand;
use iEXPackages\SupportChat\Console\SupportChatAiLearningReportCommand;
use iEXPackages\SupportChat\Console\SupportChatAiPlaybookCommand;
use iEXPackages\SupportChat\Console\SupportChatAiRegressionCheckCommand;
use iEXPackages\SupportChat\Console\SupportChatProdReadinessAuditCommand;
use iEXPackages\SupportChat\Console\SupportChatHourlyReportCommand;
use iEXPackages\SupportChat\Console\SupportChatKnowledgeAuditCommand;
use iEXPackages\SupportChat\Console\SupportChatKnowledgeImportExtractedCommand;
use iEXPackages\SupportChat\Console\SupportChatKnowledgeSearchCommand;
use iEXPackages\SupportChat\Console\SupportChatIntentDiagnosticsCommand;
use iEXPackages\SupportChat\Console\SupportChatTemplateAuditCommand;
use iEXPackages\SupportChat\Console\SupportChatTemplateImportCommand;
use iEXPackages\SupportChat\Console\SupportChatTemplateSearchCommand;
use iEXPackages\SupportChat\Console\SupportChatUxSelfTestCommand;
use iEXPackages\SupportChat\Console\SupportChatTelegramActionsSelfTestCommand;
use iEXPackages\SupportChat\Contracts\SupportChatServiceInterface;
use iEXPackages\SupportChat\Services\SupportChatService;
use iEXPackages\SupportChat\Services\SupportConversationLifecycleService;
use iEXPackages\SupportChat\Services\SupportTelegramForumTopicService;
use iEXPackages\SupportChat\Services\SupportTelegramAttachmentOutboundService;
use iEXPackages\SupportChat\Services\SupportTelegramInboundMediaService;
use iEXPackages\SupportChat\Services\SupportTelegramInboundService;
use iEXPackages\SupportChat\Services\SupportTelegramOperatorCommandService;
use iEXPackages\SupportChat\Services\SupportAiConversationOutcomeService;
use iEXPackages\SupportChat\Services\SupportAiLearningWeeklyAuditService;
use iEXPackages\SupportChat\Services\SupportAiOrderContextService;
use iEXPackages\SupportChat\Services\SupportAiDirectionContextService;
use iEXPackages\SupportChat\Services\SupportAiPlaybookService;
use iEXPackages\SupportChat\Services\SupportAiDraftService;
use iEXPackages\SupportChat\Services\SupportAiCandidateSafetyFilterService;
use iEXPackages\SupportChat\Services\SupportAiLearningAnalyzer;
use iEXPackages\SupportChat\Services\SupportAiLearningCandidateService;
use iEXPackages\SupportChat\Services\SupportAiLearningEvaluationService;
use iEXPackages\SupportChat\Services\SupportAiLearningOverlayService;
use iEXPackages\SupportChat\Services\SupportAiLearningPreviewService;
use iEXPackages\SupportChat\Services\SupportAiLearningPromotionService;
use iEXPackages\SupportChat\Services\SupportAiKnowledgeService;
use iEXPackages\SupportChat\Services\SupportAiSuggestionUxService;
use iEXPackages\SupportChat\Services\SupportAiTemplateService;
use iEXPackages\SupportChat\Services\SupportAiOperatorUsageReportService;
use iEXPackages\SupportChat\Services\SupportAiOperatorUsageService;
use iEXPackages\SupportChat\Services\SupportAiSuggestionAcceptanceService;
use iEXPackages\SupportChat\Services\SupportChatAdminRetryService;
use iEXPackages\SupportChat\Services\SupportChatDeliveryDiagnostics;
use iEXPackages\SupportChat\Services\SupportChatHealthService;
use iEXPackages\SupportChat\Services\SupportChatHourlyAnalyticsService;
use iEXPackages\SupportChat\Services\SupportChatHourlyReportService;
use iEXPackages\SupportChat\Services\SupportChatSchemaReadinessService;
use iEXPackages\SupportChat\Services\SupportTelegramDeliveryStatusService;
use iEXPackages\SupportChat\Services\SupportVisitorContextService;
use iEXPackages\SupportChat\Services\SupportTelegramOutboundService;
use iEXPackages\SupportChat\Services\SupportTelegramAiActionService;
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
        $this->app->singleton(SupportChatDeliveryDiagnostics::class);
        $this->app->singleton(SupportChatSchemaReadinessService::class);
        $this->app->singleton(SupportTelegramDeliveryStatusService::class);
        $this->app->singleton(SupportVisitorContextService::class);
        $this->app->singleton(SupportChatHealthService::class);
        $this->app->singleton(SupportChatHourlyAnalyticsService::class);
        $this->app->singleton(SupportChatHourlyReportService::class);
        $this->app->singleton(SupportChatAdminRetryService::class);
        $this->app->singleton(SupportAiConversationOutcomeService::class);
        $this->app->singleton(SupportAiOrderContextService::class);
        $this->app->singleton(SupportAiDirectionContextService::class);
        $this->app->singleton(SupportAiDraftService::class);
        $this->app->singleton(SupportAiSuggestionAcceptanceService::class);
        $this->app->singleton(SupportAiOperatorUsageService::class);
        $this->app->singleton(SupportAiOperatorUsageReportService::class);
        $this->app->singleton(SupportAiLearningWeeklyAuditService::class);
        $this->app->singleton(SupportAiPlaybookService::class);
        $this->app->singleton(SupportAiKnowledgeService::class);
        $this->app->singleton(SupportAiTemplateService::class);
        $this->app->singleton(SupportAiSuggestionUxService::class);
        $this->app->singleton(SupportTelegramAiActionService::class);
        $this->app->singleton(SupportAiLearningService::class);
        $this->app->singleton(SupportAiCandidateSafetyFilterService::class);
        $this->app->singleton(SupportAiLearningAnalyzer::class);
        $this->app->singleton(SupportAiLearningCandidateService::class);
        $this->app->singleton(SupportAiLearningOverlayService::class);
        $this->app->singleton(SupportAiLearningEvaluationService::class);
        $this->app->singleton(SupportAiLearningPreviewService::class);
        $this->app->singleton(SupportAiLearningPromotionService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(base_path('database/migrations/11.0.5'));
        $this->configureRateLimiting();
        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([
                SupportChatHourlyReportCommand::class,
                SupportChatAiDraftCommand::class,
                SupportChatAiPlaybookCommand::class,
                SupportChatAiRegressionCheckCommand::class,
                SupportChatProdReadinessAuditCommand::class,
                SupportChatAiLearningAnalyzeCommand::class,
                SupportChatAiLearningGenerateCandidatesCommand::class,
                SupportChatAiLearningReportCommand::class,
                SupportChatAiLearningEvaluateCandidatesCommand::class,
                SupportChatAiLearningPromotionReportCommand::class,
                SupportChatAiLearningPreviewGenerateCommand::class,
                SupportChatAiLearningPreviewReportCommand::class,
                SupportChatAiLearningOverlayPreviewCommand::class,
                SupportAiUsageReportCommand::class,
                SupportChatAiLearningAcceptanceReportCommand::class,
                SupportChatAiLearningOutcomeReportCommand::class,
                SupportChatAiLearningMatchingDiagnosticsCommand::class,
                SupportChatAiLearningFilterCandidatesCommand::class,
                SupportChatAiLearningCandidateReadinessReportCommand::class,
                SupportChatAiLearningWeeklyAuditCommand::class,
                SupportChatKnowledgeImportExtractedCommand::class,
                SupportChatKnowledgeSearchCommand::class,
                SupportChatKnowledgeAuditCommand::class,
                SupportChatTemplateImportCommand::class,
                SupportChatTemplateSearchCommand::class,
                SupportChatTemplateAuditCommand::class,
                SupportChatIntentDiagnosticsCommand::class,
                SupportChatUxSelfTestCommand::class,
                SupportChatTelegramActionsSelfTestCommand::class,
            ]);
        }
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
