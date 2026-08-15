<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Http\Controllers;

use iEXPackages\SupportChat\Services\SupportChatDiagnosticsLog;
use iEXPackages\SupportChat\Services\SupportChatMetrics;
use iEXPackages\SupportChat\Services\SupportTelegramInboundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class SupportTelegramWebhookController
{
    public function __construct(
        private readonly SupportTelegramInboundService $inbound,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        try {
            /** @var array<string, mixed> $payload */
            $payload = $request->all();
            SupportChatMetrics::recordWebhookReceived();
            SupportChatDiagnosticsLog::webhookReceived([
                'update_id' => $payload['update_id'] ?? null,
                'update_type' => isset($payload['callback_query']) && is_array($payload['callback_query'])
                    ? 'callback_query'
                    : (isset($payload['message']) && is_array($payload['message']) ? 'message' : 'other'),
            ]);
            $this->inbound->processWebhookUpdate($payload);
        } catch (Throwable $e) {
            SupportChatDiagnosticsLog::webhookFailed([
                'reason' => SupportChatDiagnosticsLog::sanitizeError($e->getMessage()),
                'error_code' => 'webhook_exception',
            ]);

            return response()->json(['ok' => false], 500);
        }

        return response()->json(['ok' => true]);
    }
}
