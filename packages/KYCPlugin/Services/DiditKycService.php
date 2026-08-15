<?php

declare(strict_types=1);

namespace iEXPackages\KYCPlugin\Services;

use App\Models\KycProviderSession;
use App\Models\User;
use iEXPackages\KYCPlugin\Drivers\Didit\DiditClient;
use iEXPackages\KYCPlugin\Drivers\Didit\DiditStatusNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Server-bound Didit session lifecycle for identity KYC.
 */
final class DiditKycService
{
    public function __construct(
        private readonly ?DiditClient $client = null,
    ) {
    }

    private function client(): DiditClient
    {
        return $this->client ?? DiditClient::fromConfig();
    }

    /**
     * Create or reuse an active Didit session for the authenticated user.
     *
     * @return array{provider:string,status:string,verification_url:?string,can_start:bool,session_id:?string}
     */
    public function createVerificationSession(User $user): array
    {
        if ((int) $user->is_verify_account === 1) {
            return [
                'provider' => KycProviderResolver::PROVIDER_DIDIT,
                'status' => 'approved',
                'verification_url' => null,
                'can_start' => false,
                'session_id' => null,
            ];
        }

        $approved = KycProviderSession::query()
            ->where('user_id', (int) $user->id)
            ->where('provider', KycProviderResolver::PROVIDER_DIDIT)
            ->where('normalized_status', 'approved')
            ->orderByDesc('id')
            ->first();

        if ($approved) {
            return [
                'provider' => KycProviderResolver::PROVIDER_DIDIT,
                'status' => 'approved',
                'verification_url' => null,
                'can_start' => false,
                'session_id' => null,
            ];
        }

        $active = KycProviderSession::query()
            ->where('user_id', (int) $user->id)
            ->where('provider', KycProviderResolver::PROVIDER_DIDIT)
            ->whereIn('normalized_status', ['not_started', 'pending', 'manual_review'])
            ->orderByDesc('id')
            ->first();

        if ($active && filled($active->verification_url)) {
            // Sticky reuse: reconcile against authenticated provider truth so a missed
            // or rejected webhook cannot leave the customer UI stuck on not_started.
            // Does not create a second session; retrieve maps only this user's row.
            try {
                $synced = $this->retrieveVerificationStatus($user);

                return [
                    'provider' => $synced['provider'],
                    'status' => $synced['status'],
                    'verification_url' => $synced['verification_url'],
                    'can_start' => $synced['can_start'],
                    'session_id' => (string) $active->provider_session_id,
                ];
            } catch (Throwable $e) {
                Log::warning('didit_sticky_reconcile_failed', [
                    'user_id' => (int) $user->id,
                    'session_pk' => (int) $active->id,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'provider' => KycProviderResolver::PROVIDER_DIDIT,
                    'status' => (string) $active->normalized_status,
                    'verification_url' => (string) $active->verification_url,
                    'can_start' => in_array($active->normalized_status, ['not_started', 'pending'], true),
                    'session_id' => (string) $active->provider_session_id,
                ];
            }
        }

        $workflowId = trim((string) config('kyc.didit.workflow_id', ''));
        $callback = trim((string) config('kyc.didit.callback_url', ''));
        if ($workflowId === '') {
            throw new RuntimeException('Didit workflow_id is not configured');
        }

        $reference = (string) Str::uuid();
        $payload = [
            'workflow_id' => $workflowId,
            'vendor_data' => $reference,
        ];
        if ($callback !== '') {
            $payload['callback'] = $callback;
        }

        $created = $this->client()->createSession($payload);
        $sessionId = (string) ($created['session_id'] ?? '');
        $url = (string) ($created['url'] ?? $created['verification_url'] ?? '');
        $providerStatus = (string) ($created['status'] ?? 'Not Started');
        $normalized = DiditStatusNormalizer::normalize($providerStatus);

        if ($sessionId === '' || $url === '') {
            throw new RuntimeException('Didit session create missing session_id or url');
        }

        $row = KycProviderSession::query()->create([
            'user_id' => (int) $user->id,
            'provider' => KycProviderResolver::PROVIDER_DIDIT,
            'provider_session_id' => $sessionId,
            'provider_workflow_id' => $workflowId,
            'provider_reference' => $reference,
            'normalized_status' => $normalized,
            'provider_status' => $providerStatus,
            'verification_url' => $url,
            'started_at' => now(),
            'last_synced_at' => now(),
        ]);

        $this->writeKycLog((int) $user->id, 'session_created', $normalized, [
            'session_id' => $sessionId,
            'provider_reference' => $reference,
        ]);

        return [
            'provider' => KycProviderResolver::PROVIDER_DIDIT,
            'status' => (string) $row->normalized_status,
            'verification_url' => (string) $row->verification_url,
            'can_start' => true,
            'session_id' => (string) $row->provider_session_id,
        ];
    }

    /**
     * @return array{provider:string,status:string,verification_url:?string,can_start:bool,is_verified:bool}
     */
    public function retrieveVerificationStatus(User $user, ?string $providedSessionId = null): array
    {
        $session = null;
        if (is_string($providedSessionId) && trim($providedSessionId) !== '') {
            $session = KycProviderSession::query()
                ->where('provider', KycProviderResolver::PROVIDER_DIDIT)
                ->where('provider_session_id', trim($providedSessionId))
                ->first();

            if (!$session || (int) $session->user_id !== (int) $user->id) {
                throw new RuntimeException('Forbidden');
            }
        } else {
            $session = KycProviderSession::query()
                ->where('user_id', (int) $user->id)
                ->where('provider', KycProviderResolver::PROVIDER_DIDIT)
                ->orderByDesc('id')
                ->first();
        }

        if (!$session) {
            return [
                'provider' => KycProviderResolver::PROVIDER_DIDIT,
                'status' => 'not_started',
                'verification_url' => null,
                'can_start' => true,
                'is_verified' => (int) $user->is_verify_account === 1,
            ];
        }

        try {
            $decision = $this->client()->retrieveDecision((string) $session->provider_session_id);
            $this->applyAuthoritativeDecision($session, $decision, null);
            $session->refresh();
        } catch (Throwable $e) {
            // Fail closed for gate mutations; still return last known local status.
            Log::warning('didit_status_retrieve_failed', [
                'user_id' => (int) $user->id,
                'session_pk' => $session->id,
                'error' => $e->getMessage(),
            ]);
        }

        $user->refresh();

        return [
            'provider' => KycProviderResolver::PROVIDER_DIDIT,
            'status' => (string) $session->normalized_status,
            'verification_url' => $session->isActive() ? (string) $session->verification_url : null,
            'can_start' => $session->isActive() && in_array($session->normalized_status, ['not_started', 'pending'], true),
            'is_verified' => (int) $user->is_verify_account === 1,
        ];
    }

    /**
     * Apply webhook/API decision with mapping checks. Returns true if applied.
     *
     * @param array<string, mixed> $decisionOrEvent
     */
    public function applyAuthoritativeDecision(
        KycProviderSession $session,
        array $decisionOrEvent,
        ?string $eventId,
    ): bool {
        $sessionId = (string) ($decisionOrEvent['session_id'] ?? $session->provider_session_id);
        if ($sessionId !== '' && !hash_equals((string) $session->provider_session_id, $sessionId)) {
            Log::warning('didit_provider_mapping_conflict', [
                'reason' => 'session_id_mismatch',
                'session_pk' => $session->id,
            ]);
            $this->writeKycLog((int) $session->user_id, 'provider_mapping_conflict', 'conflict', [
                'reason' => 'session_id_mismatch',
            ]);

            return false;
        }

        $vendorData = (string) ($decisionOrEvent['vendor_data'] ?? '');
        if ($vendorData !== '' && !hash_equals((string) $session->provider_reference, $vendorData)) {
            Log::warning('didit_provider_mapping_conflict', [
                'reason' => 'vendor_data_mismatch',
                'session_pk' => $session->id,
            ]);
            $this->writeKycLog((int) $session->user_id, 'provider_mapping_conflict', 'conflict', [
                'reason' => 'vendor_data_mismatch',
            ]);

            return false;
        }

        if ($eventId !== null && $eventId !== '' && (string) $session->last_event_id === $eventId) {
            return true; // idempotent duplicate
        }

        $providerStatus = (string) ($decisionOrEvent['status'] ?? $session->provider_status ?? '');
        $normalized = DiditStatusNormalizer::normalize($providerStatus);

        $session->provider_status = $providerStatus !== '' ? $providerStatus : $session->provider_status;
        $session->normalized_status = $normalized;
        $session->last_synced_at = now();
        if ($eventId !== null && $eventId !== '') {
            $session->last_event_id = $eventId;
        }
        if (DiditStatusNormalizer::maySetVerified($normalized) || DiditStatusNormalizer::isTerminalRejected($normalized)) {
            $session->completed_at = $session->completed_at ?? now();
        }
        $session->save();

        $user = User::query()->find((int) $session->user_id);
        if (!$user) {
            return false;
        }

        if (DiditStatusNormalizer::maySetVerified($normalized)) {
            if ((int) $user->is_verify_account !== 1) {
                $user->is_verify_account = 1;
                $user->save();
            }
        }
        // Declined/pending/etc. must not bypass; do not clear an already-approved account here.

        $this->writeKycLog((int) $user->id, 'decision', $normalized, [
            'session_id' => (string) $session->provider_session_id,
            'provider_status' => $providerStatus,
        ]);

        return true;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function writeKycLog(int $userId, string $event, string $status, array $meta = []): void
    {
        try {
            DB::table('kyc_logs')->insert([
                'provider' => KycProviderResolver::PROVIDER_DIDIT,
                'event' => $event,
                'status' => $status,
                'user_id' => $userId,
                'response_data' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable) {
            // never break KYC flow on log failure
        }
    }
}
