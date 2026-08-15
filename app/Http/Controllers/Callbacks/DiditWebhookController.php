<?php

declare(strict_types=1);

namespace App\Http\Controllers\Callbacks;

use App\Http\Controllers\Controller;
use App\Models\KycProviderSession;
use App\Models\ProviderWebhookEvent;
use iEXPackages\KYCPlugin\Drivers\Didit\DiditClient;
use iEXPackages\KYCPlugin\Drivers\Didit\DiditWebhookSignature;
use iEXPackages\KYCPlugin\Services\DiditKycService;
use iEXPackages\KYCPlugin\Services\KycProviderResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DiditWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $raw = $request->getContent() ?: '';
        $requestId = (string) ($request->headers->get('X-Request-ID') ?: '');

        Log::info('didit_webhook_received', [
            'request_id' => $requestId !== '' ? $requestId : null,
            'content_length' => strlen($raw),
            'has_signature' => filled($request->header('X-Signature-V2'))
                || filled($request->header('X-Signature'))
                || filled($request->header('X-Signature-Simple')),
        ]);

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return response('invalid json', 400);
        }

        $secret = (string) config('kyc.didit.webhook_secret', '');
        $valid = DiditWebhookSignature::isValid(
            secret: $secret,
            rawBody: $raw,
            decodedJson: $decoded,
            signatureV2: $request->header('X-Signature-V2'),
            signatureRaw: $request->header('X-Signature'),
            signatureSimple: $request->header('X-Signature-Simple'),
            timestampHeader: $request->header('X-Timestamp'),
        );

        if (!$valid) {
            Log::info('didit_webhook_signature_rejected', [
                'request_id' => $requestId !== '' ? $requestId : null,
                'has_v2' => filled($request->header('X-Signature-V2')),
                'has_raw' => filled($request->header('X-Signature')),
                'has_simple' => filled($request->header('X-Signature-Simple')),
            ]);

            return response('unauthorized', 401);
        }

        $sessionId = trim((string) ($decoded['session_id'] ?? ''));
        if ($sessionId === '') {
            return response('missing session', 400);
        }
        $sessionMasked = strlen($sessionId) > 8
            ? (substr($sessionId, 0, 4) . '…' . substr($sessionId, -4))
            : '********';

        $eventId = trim((string) (
            $decoded['event_id']
            ?? $decoded['webhook_id']
            ?? $request->header('X-Webhook-Id')
            ?? ($sessionId . ':' . (string) ($decoded['status'] ?? '') . ':' . (string) ($decoded['timestamp'] ?? $decoded['created_at'] ?? ''))
        ));

        if (Schema::hasTable('provider_webhook_events') && $eventId !== '') {
            $existing = ProviderWebhookEvent::query()
                ->where('provider', KycProviderResolver::PROVIDER_DIDIT)
                ->where('event_id', $eventId)
                ->first();
            if ($existing && $existing->status === ProviderWebhookEvent::STATUS_PROCESSED) {
                Log::info('didit_webhook_duplicate', [
                    'request_id' => $requestId !== '' ? $requestId : null,
                    'session_id_masked' => $sessionMasked,
                    'event_id' => $eventId,
                ]);

                return response('ok', 200);
            }
        }

        $session = KycProviderSession::query()
            ->where('provider', KycProviderResolver::PROVIDER_DIDIT)
            ->where('provider_session_id', $sessionId)
            ->first();

        if (!$session) {
            Log::info('didit_webhook_unknown_session', [
                'request_id' => $requestId !== '' ? $requestId : null,
                'session_id_masked' => $sessionMasked,
            ]);
            $this->rememberEvent($eventId, null, 'unresolved');

            return response('ok', 200); // do not mutate
        }

        try {
            // Prefer authoritative retrieve when practical.
            $decision = $decoded;
            try {
                $decision = DiditClient::fromConfig()->retrieveDecision($sessionId);
                if (!isset($decision['session_id'])) {
                    $decision['session_id'] = $sessionId;
                }
                if (!isset($decision['vendor_data'])) {
                    $decision['vendor_data'] = $decoded['vendor_data'] ?? $session->provider_reference;
                }
            } catch (Throwable $e) {
                // Fall back to signed webhook envelope status only (no raw PII logging).
                Log::warning('didit_webhook_retrieve_fallback', [
                    'session_pk' => $session->id,
                    'error' => $e->getMessage(),
                ]);
                if (!isset($decision['vendor_data'])) {
                    $decision['vendor_data'] = $session->provider_reference;
                }
            }

            $applied = (new DiditKycService())->applyAuthoritativeDecision(
                $session,
                $decision,
                $eventId !== '' ? $eventId : null,
            );
            $this->rememberEvent($eventId, (int) $session->id, $applied ? 'processed' : 'conflict');
            Log::info($applied ? 'didit_webhook_status_applied' : 'didit_webhook_mapping_conflict', [
                'request_id' => $requestId !== '' ? $requestId : null,
                'session_pk' => $session->id,
                'session_id_masked' => $sessionMasked,
                'user_id' => (int) $session->user_id,
                'normalized_status' => (string) $session->fresh()?->normalized_status,
            ]);
        } catch (Throwable $e) {
            Log::error('didit_webhook_processing_failed', [
                'request_id' => $requestId !== '' ? $requestId : null,
                'session_pk' => $session->id,
                'session_id_masked' => $sessionMasked,
                'error' => $e->getMessage(),
            ]);
            $this->rememberEvent($eventId, (int) $session->id, 'failed');

            return response('error', 500);
        }

        return response('ok', 200);
    }

    private function rememberEvent(string $eventId, ?int $sessionPk, string $status): void
    {
        if ($eventId === '' || !Schema::hasTable('provider_webhook_events')) {
            return;
        }

        try {
            $statusMap = [
                'processed' => ProviderWebhookEvent::STATUS_PROCESSED,
                'failed' => ProviderWebhookEvent::STATUS_FAILED,
                'unresolved' => ProviderWebhookEvent::STATUS_UNRESOLVED,
                'conflict' => ProviderWebhookEvent::STATUS_CONFLICT,
            ];
            ProviderWebhookEvent::query()->updateOrCreate(
                [
                    'provider' => KycProviderResolver::PROVIDER_DIDIT,
                    'event_id' => $eventId,
                ],
                [
                    'status' => $statusMap[$status] ?? ProviderWebhookEvent::STATUS_PROCESSED,
                    'task_id' => null,
                    'event_type' => 'didit.kyc',
                    'payload_hash' => hash('sha256', $eventId . ':' . (string) $sessionPk),
                    'processed_at' => now(),
                ]
            );
        } catch (Throwable) {
            // ignore persistence issues for webhook ack path
        }
    }
}
