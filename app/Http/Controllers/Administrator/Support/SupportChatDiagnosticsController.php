<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administrator\Support;

use App\Http\Controllers\Controller;
use App\Models\SupportAttachment;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use iEXPackages\SupportChat\Services\SupportAiLearningWeeklyAuditService;
use iEXPackages\SupportChat\Services\SupportChatAdminRetryService;
use iEXPackages\SupportChat\Services\SupportChatHealthService;
use iEXPackages\SupportChat\Services\SupportChatSchemaReadinessService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class SupportChatDiagnosticsController extends Controller
{
    public function __construct(
        private readonly SupportChatHealthService $health,
        private readonly SupportChatAdminRetryService $retry,
        private readonly SupportChatSchemaReadinessService $schemaReadiness,
        private readonly SupportAiLearningWeeklyAuditService $weeklyAudit,
    ) {}

    public function index(): View
    {
        $health = $this->health->snapshot();

        return view('admin.support.diagnostics', [
            'health' => $health,
            'rows' => $this->health->diagnosticRows(60),
            'supportChatDiagnosticsAvailable' => (bool) ($health['diagnostics_available'] ?? false),
            'telegramDeliverySummary' => $health['telegram_delivery_summary'] ?? null,
            'aiLearningMetrics' => $this->weeklyAudit->widgetSnapshot(),
        ]);
    }

    public function health(): JsonResponse
    {
        return response()->json($this->health->snapshot());
    }

    public function retryMessage(Request $request, SupportMessage $message): RedirectResponse
    {
        if (! $this->schemaReadiness->isDiagnosticsAvailable()) {
            return back()->with('error', 'Delivery telemetry unavailable (schema mismatch). Apply pending Support Chat migrations.');
        }

        try {
            $this->retry->retryTelegramMessage($message, (int) $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Telegram delivery retry queued for message #'.$message->id);
    }

    public function recreateTopic(Request $request, SupportConversation $conversation): RedirectResponse
    {
        try {
            $this->retry->recreateForumTopic($conversation, (int) $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Telegram forum topic created for '.$conversation->public_support_id);
    }

    public function retryAttachment(Request $request, SupportAttachment $attachment): RedirectResponse
    {
        if (! $this->schemaReadiness->isDiagnosticsAvailable()) {
            return back()->with('error', 'Delivery telemetry unavailable (schema mismatch). Apply pending Support Chat migrations.');
        }

        try {
            $this->retry->retryAttachmentTelegram($attachment, (int) $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Telegram attachment retry queued #'.$attachment->id);
    }
}
