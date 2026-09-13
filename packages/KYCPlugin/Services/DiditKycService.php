<?php

declare(strict_types=1);

namespace iEXPackages\KYCPlugin\Services;

use App\Jobs\Telegram\TelegramIdentityVerifiedJob;
use App\Models\KycProviderSession;
use App\Models\User;
use iEXPackages\KYCPlugin\Drivers\Didit\DiditClient;
use iEXPackages\KYCPlugin\Drivers\Didit\DiditStatusNormalizer;
use iEXPackages\KYCPlugin\Drivers\Didit\DiditStatusTransition;
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
        return DB::transaction(function () use ($user) {
            User::query()->whereKey((int) $user->id)->lockForUpdate()->first();
            $user->refresh();

            return $this->createVerificationSessionLocked($user);
        });
    }

    /**
     * Local status only — never creates a remote Didit session.
     *
     * @return array{provider:string,status:string,verification_url:?string,can_start:bool,session_id:?string}
     */
    public function readLocalStatus(User $user): array
    {
        if ((int) $user->is_verify_account === 1) {
            $approved = $this->latestSession((int) $user->id, ['approved']);

            return [
                'provider' => KycProviderResolver::PROVIDER_DIDIT,
                'status' => 'approved',
                'verification_url' => null,
                'can_start' => false,
                'session_id' => $approved ? (string) $approved->provider_session_id : null,
            ];
        }

        $active = $this->latestSession((int) $user->id, ['not_started', 'pending', 'manual_review']);
        if ($active) {
            $canStart = in_array($active->normalized_status, ['not_started', 'pending'], true);

            return [
                'provider' => KycProviderResolver::PROVIDER_DIDIT,
                'status' => (string) $active->normalized_status,
                'verification_url' => filled($active->verification_url) ? (string) $active->verification_url : null,
                'can_start' => $canStart && filled($active->verification_url),
                'session_id' => (string) $active->provider_session_id,
            ];
        }

        $latest = $this->latestSession((int) $user->id);
        $status = $latest ? (string) $latest->normalized_status : 'not_started';
        $retryable = in_array($status, ['not_started', 'rejected', 'expired', 'abandoned', 'unknown'], true);

        return [
            'provider' => KycProviderResolver::PROVIDER_DIDIT,
            'status' => $status,
            'verification_url' => null,
            'can_start' => $retryable || $latest === null,
            'session_id' => $latest ? (string) $latest->provider_session_id : null,
        ];
    }

    /**
     * @return array{provider:string,status:string,verification_url:?string,can_start:bool,session_id:?string}
     */
    private function createVerificationSessionLocked(User $user): array
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

        $approved = $this->latestSession((int) $user->id, ['approved']);

        if ($approved) {
            return [
                'provider' => KycProviderResolver::PROVIDER_DIDIT,
                'status' => 'approved',
                'verification_url' => null,
                'can_start' => false,
                'session_id' => null,
            ];
        }

        $active = $this->latestSession((int) $user->id, ['not_started', 'pending', 'manual_review']);

        if ($active && filled($active->verification_url)) {
            // Sticky reuse: reconcile against authenticated provider truth so a missed
            // webhook cannot leave the customer UI stuck. GET status never does this.
            try {
                $synced = $this->retrieveVerificationStatus($user);
                $user->refresh();
                if ((int) $user->is_verify_account === 1 || ($synced['status'] ?? '') === 'approved') {
                    return [
                        'provider' => KycProviderResolver::PROVIDER_DIDIT,
                        'status' => 'approved',
                        'verification_url' => null,
                        'can_start' => false,
                        'session_id' => (string) ($synced['session_id'] ?? $active->provider_session_id),
                    ];
                }
                $stillReusable = in_array((string) ($synced['status'] ?? ''), ['not_started', 'pending', 'manual_review'], true)
                    && filled($synced['verification_url'] ?? null);
                if ($stillReusable) {
                    return [
                        'provider' => $synced['provider'],
                        'status' => $synced['status'],
                        'verification_url' => $synced['verification_url'],
                        'can_start' => $synced['can_start'],
                        'session_id' => (string) $active->provider_session_id,
                    ];
                }
                // Terminal/expired after reconcile — fall through and create a new session.
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
     * @param  list<string>|null  $statuses
     */
    private function latestSession(int $userId, ?array $statuses = null): ?KycProviderSession
    {
        $query = KycProviderSession::query()
            ->where('user_id', $userId)
            ->where('provider', KycProviderResolver::PROVIDER_DIDIT)
            ->orderByDesc('id');
        if ($statuses !== null) {
            $query->whereIn('normalized_status', $statuses);
        }

        return $query->first();
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
            'session_id' => (string) $session->provider_session_id,
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

        return DB::transaction(function () use ($session, $decisionOrEvent, $eventId): bool {
            $lockedSession = KycProviderSession::query()
                ->whereKey((int) $session->id)
                ->lockForUpdate()
                ->first();
            if (!$lockedSession) {
                return false;
            }

            if ($eventId !== null && $eventId !== '' && (string) $lockedSession->last_event_id === $eventId) {
                return true;
            }

            $providerStatus = (string) ($decisionOrEvent['status'] ?? $lockedSession->provider_status ?? '');
            $normalized = DiditStatusNormalizer::normalize($providerStatus);
            $current = (string) $lockedSession->normalized_status;
            $overwrite = DiditStatusTransition::mayOverwriteSession($current, $normalized);

            $lockedSession->last_synced_at = now();
            if ($eventId !== null && $eventId !== '') {
                $lockedSession->last_event_id = $eventId;
            }

            if (!$overwrite) {
                $lockedSession->save();
                $this->writeKycLog((int) $lockedSession->user_id, 'status_transition_rejected', $normalized, [
                    'session_id' => (string) $lockedSession->provider_session_id,
                    'current' => $current,
                    'incoming' => $normalized,
                ]);
                Log::info('didit_status_transition_rejected', [
                    'session_pk' => $lockedSession->id,
                    'user_id' => (int) $lockedSession->user_id,
                    'current' => $current,
                    'incoming' => $normalized,
                ]);
                $session->normalized_status = $lockedSession->normalized_status;
                $session->last_event_id = $lockedSession->last_event_id;

                return true;
            }

            $lockedSession->provider_status = $providerStatus !== '' ? $providerStatus : $lockedSession->provider_status;
            $lockedSession->normalized_status = $normalized;
            if (DiditStatusNormalizer::maySetVerified($normalized) || DiditStatusNormalizer::isTerminalRejected($normalized)) {
                $lockedSession->completed_at = $lockedSession->completed_at ?? now();
            }
            $lockedSession->save();
            $session->normalized_status = $lockedSession->normalized_status;
            $session->last_event_id = $lockedSession->last_event_id;

            $user = User::query()->whereKey((int) $lockedSession->user_id)->lockForUpdate()->first();
            if (!$user) {
                return false;
            }

            $firstApproval = false;
            if (DiditStatusNormalizer::maySetVerified($normalized)) {
                if ((int) $user->is_verify_account !== 1) {
                    $user->is_verify_account = 1;
                    $user->save();
                    $firstApproval = true;
                }
            }
            // Declined/pending/etc. must not bypass; do not clear an already-approved account here.

            $this->writeKycLog((int) $user->id, 'decision', $normalized, [
                'session_id' => (string) $lockedSession->provider_session_id,
                'provider_status' => $providerStatus,
            ]);

            if ($firstApproval) {
                $this->enqueueApprovalNotification((int) $user->id, (int) $lockedSession->id);
            }

            return true;
        });
    }

    private function enqueueApprovalNotification(int $userId, int $sessionPk): void
    {
        try {
            TelegramIdentityVerifiedJob::dispatch($userId, $sessionPk);
            Log::info('telegram_identity_verified_enqueued', [
                'user_id' => $userId,
                'session_pk' => $sessionPk,
            ]);
        } catch (Throwable $e) {
            Log::error('telegram_identity_verified_enqueue_failed', [
                'user_id' => $userId,
                'session_pk' => $sessionPk,
                'error' => $e->getMessage(),
            ]);
        }
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
