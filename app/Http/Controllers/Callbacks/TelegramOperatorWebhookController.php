<?php

declare(strict_types=1);

namespace App\Http\Controllers\Callbacks;

use App\Services\TelegramOperator\TelegramOrderOperatorWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

final class TelegramOperatorWebhookController
{
    public function __construct(
        private readonly TelegramOrderOperatorWorkflowService $workflow,
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        try {
            /** @var array<string, mixed> $payload */
            $payload = $request->all();
            $this->workflow->handleUpdate($payload);
        } catch (Throwable $e) {
            Log::error('telegram_operator_webhook_failed', [
                'exception' => $e::class,
            ]);

            // Acknowledge to Telegram to avoid endless retries on handler bugs;
            // operator mutations themselves remain fail-closed inside workflow.
            return response()->json(['ok' => false], 200);
        }

        return response()->json(['ok' => true]);
    }
}
