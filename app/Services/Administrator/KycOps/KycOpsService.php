<?php

declare(strict_types=1);

namespace App\Services\Administrator\KycOps;

use App\Models\KycProviderSession;
use App\Models\SumsubId;
use App\Models\User;
use App\Models\UserVerification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use iEXPackages\KYCPlugin\Drivers\SumSub\SumSubDriver;
use iEXPackages\KYCPlugin\Services\DiditKycService;
use iEXPackages\KYCPlugin\Services\KycProviderResolver;
use RuntimeException;
use Throwable;

/**
 * Read-mostly KYC operations queries + safe reconcile actions.
 * Does not approve/decline, mutate routing, or expose secrets/PII images.
 */
final class KycOpsService
{
    public function __construct(
        private readonly KycProviderResolver $resolver,
        private readonly DiditKycService $didit,
    ) {}

    public function dashboard(): array
    {
        $providerCounts = [
            'not_started' => 0,
            'pending' => 0,
            'manual_review' => 0,
            'approved' => 0,
            'declined' => 0,
            'expired' => 0,
            'other' => 0,
        ];

        if (Schema::hasTable('kyc_provider_sessions')) {
            $rows = KycProviderSession::query()
                ->select('normalized_status', DB::raw('COUNT(*) as c'))
                ->groupBy('normalized_status')
                ->pluck('c', 'normalized_status');
            foreach ($rows as $status => $count) {
                $key = array_key_exists((string) $status, $providerCounts) ? (string) $status : 'other';
                $providerCounts[$key] += (int) $count;
            }
        }

        $manualPending = Schema::hasTable('user_verification')
            ? (int) UserVerification::query()->where('status', 0)->count()
            : 0;

        $webhook = $this->webhookHealth();
        $attention = $this->attentionItems(limit: 8);

        return [
            'generated_at' => now()->toIso8601String(),
            'configured_provider' => $this->resolver->configuredProvider(),
            'allowlist_empty' => (config('kyc.didit_allowlist_user_ids') ?: []) === [],
            'totals' => [
                'provider_sessions' => Schema::hasTable('kyc_provider_sessions')
                    ? (int) KycProviderSession::query()->count()
                    : 0,
                'manual_pending' => $manualPending,
                'sumsub_rows' => Schema::hasTable('sumsub_ids')
                    ? (int) SumsubId::query()->count()
                    : 0,
                'verified_users' => (int) User::query()->where('is_verify_account', 1)->count(),
            ],
            'status_counts' => $providerCounts,
            'webhook' => $webhook,
            'attention_preview' => $attention,
            'recent_approvals' => $this->recentApprovals(8),
            'recent_failures' => $this->recentFailures(8),
            'mismatch_count' => count($this->statusMismatchItems(limit: 100)),
            'secrets' => [
                'didit_api_key_set' => trim((string) config('kyc.didit.api_key', '')) !== '',
                'didit_webhook_secret_set' => trim((string) config('kyc.didit.webhook_secret', '')) !== '',
                'didit_workflow_id_set' => trim((string) config('kyc.didit.workflow_id', '')) !== '',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function listCases(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $perPage = max(10, min(100, $perPage));
        $sort = in_array(($filters['sort'] ?? ''), ['created_at', 'last_update', 'user_id', 'provider', 'status'], true)
            ? (string) $filters['sort']
            : 'last_update';
        $dir = strtolower((string) ($filters['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $union = $this->casesUnionQuery($filters);
        $total = (int) DB::query()->fromSub($union, 'kyc_cases')->count();

        $page = max(1, (int) ($filters['page'] ?? 1));
        $rows = DB::query()
            ->fromSub($union, 'kyc_cases')
            ->orderBy($sort, $dir)
            ->orderByDesc('source_id')
            ->forPage($page, $perPage)
            ->get();

        $userIds = $rows->pluck('user_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $users = User::query()->whereIn('id', $userIds)->get()->keyBy('id');
        $lastWebhooks = $this->lastWebhookByUser($userIds);

        $items = $rows->map(function ($row) use ($users, $lastWebhooks) {
            $user = $users->get((int) $row->user_id);
            $localApproved = $user ? (int) $user->is_verify_account === 1 : false;
            $status = (string) $row->status;
            $providerApproved = $status === 'approved';
            $mismatch = $providerApproved && ! $localApproved;
            $webhook = $lastWebhooks[(int) $row->user_id] ?? null;

            return [
                'case_key' => (string) $row->case_key,
                'source' => (string) $row->source,
                'source_id' => (int) $row->source_id,
                'user_id' => (int) $row->user_id,
                'email_masked' => $this->maskEmail($user?->email),
                'provider' => (string) $row->provider,
                'status' => $status,
                'stage' => $this->stageLabel($status, (string) $row->source),
                'is_verify_account' => $localApproved,
                'session_created_at' => $row->created_at,
                'last_provider_update' => $row->last_update,
                'last_webhook_at' => $webhook['at'] ?? null,
                'last_webhook_status' => $webhook['status'] ?? null,
                'risk' => $this->riskIndicator($status, $localApproved, $mismatch, $webhook['status'] ?? null),
                'routing_reason' => null,
            ];
        });

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
        );
        $paginator->setPath(route('admin.kyc-ops.index'));
        $paginator->appends(collect($filters)->except('page')->all());

        return $paginator;
    }

    public function caseDetail(string $caseKey): array
    {
        [$source, $id] = $this->parseCaseKey($caseKey);
        $userId = 0;
        $provider = $source;
        $sessionMasked = null;
        $localStatus = null;
        $providerStatus = null;
        $createdAt = null;
        $updatedAt = null;
        $verificationUrl = null;
        $sourceId = $id;

        if ($source === 'didit') {
            $session = KycProviderSession::query()->findOrFail($id);
            $userId = (int) $session->user_id;
            $provider = (string) $session->provider;
            $sessionMasked = $this->maskSessionId((string) $session->provider_session_id);
            $localStatus = (string) $session->normalized_status;
            $providerStatus = (string) ($session->provider_status ?? '');
            $createdAt = optional($session->created_at)?->toIso8601String();
            $updatedAt = optional($session->last_synced_at ?? $session->updated_at)?->toIso8601String();
            $verificationUrl = $session->isActive() ? (string) $session->verification_url : null;
        } elseif ($source === 'sumsub') {
            $row = SumsubId::query()->findOrFail($id);
            $userId = (int) $row->user_id;
            $provider = trim((string) ($row->provider ?: 'sumsub')) ?: 'sumsub';
            $sessionMasked = $this->maskSessionId((string) $row->applicant_id);
            $localStatus = (string) ($row->status ?: ((int) $row->is_completed === 1 ? 'approved' : 'pending'));
            $providerStatus = $localStatus;
            $createdAt = optional($row->created_at)?->toIso8601String();
            $updatedAt = optional($row->updated_at)?->toIso8601String();
        } elseif ($source === 'manual') {
            $row = UserVerification::query()->findOrFail($id);
            $userId = (int) $row->user_id;
            $provider = 'manual';
            $localStatus = match ((int) $row->status) {
                1 => 'manual_approved',
                2 => 'manual_declined',
                default => 'manual_pending',
            };
            $providerStatus = $localStatus;
            $createdAt = optional($row->created_at)?->toIso8601String();
            $updatedAt = optional($row->updated_at)?->toIso8601String();
        } else {
            throw new RuntimeException('Unknown case');
        }

        $user = User::query()->findOrFail($userId);
        $resolved = $this->resolver->resolveForUser($user);
        $routingReason = $this->routingReason($user, $resolved);

        return [
            'case_key' => $caseKey,
            'source' => $source,
            'source_id' => $sourceId,
            'identity' => [
                'user_id' => $userId,
                'email_masked' => $this->maskEmail($user->email),
                'is_verify_account' => (int) $user->is_verify_account === 1,
                'account_state' => (int) $user->is_verify_account === 1 ? 'verified' : 'unverified',
            ],
            'provider' => [
                'current' => $provider,
                'resolved' => $resolved,
                'routing_reason' => $routingReason,
                'session_id_masked' => $sessionMasked,
                'session_created_at' => $createdAt,
                'last_update' => $updatedAt,
                'has_open_session_url' => is_string($verificationUrl) && $verificationUrl !== '',
            ],
            'verification' => [
                'local_status' => $localStatus,
                'provider_status' => $providerStatus,
                'is_verify_account' => (int) $user->is_verify_account === 1,
                'exchange_eligible' => (int) $user->is_verify_account === 1,
                'stage' => $this->stageLabel((string) $localStatus, $source),
            ],
            'timeline' => $this->timelineForUser($userId, $source, $sourceId),
            'safe_actions' => $this->safeActionsFor($source),
        ];
    }

    public function diagnostics(): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'current_provider' => $this->resolver->configuredProvider(),
            'allowlist_empty' => (config('kyc.didit_allowlist_user_ids') ?: []) === [],
            'webhook' => $this->webhookHealth(),
            'retrieve' => $this->retrieveHealth(),
            'status' => $this->dashboard()['status_counts'],
            'manual_pending' => Schema::hasTable('user_verification')
                ? (int) UserVerification::query()->where('status', 0)->count()
                : 0,
            'secrets_configured' => [
                'didit_api_key' => trim((string) config('kyc.didit.api_key', '')) !== '',
                'didit_webhook_secret' => trim((string) config('kyc.didit.webhook_secret', '')) !== '',
                'didit_workflow_id' => trim((string) config('kyc.didit.workflow_id', '')) !== '',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function attentionItems(int $limit = 50): array
    {
        $items = [];
        $items = array_merge($items, $this->statusMismatchItems(limit: $limit));
        $items = array_merge($items, $this->pendingTooLongItems(limit: $limit));
        $items = array_merge($items, $this->duplicateSessionItems(limit: $limit));
        $items = array_merge($items, $this->manualPendingItems(limit: $limit));
        $items = array_merge($items, $this->webhookFailureItems(limit: $limit));
        $items = array_merge($items, $this->mappingConflictItems(limit: $limit));

        usort($items, static fn ($a, $b) => strcmp((string) ($b['detected_at'] ?? ''), (string) ($a['detected_at'] ?? '')));

        return array_slice($items, 0, $limit);
    }

    /**
     * Safe reconcile — wraps existing provider retrieve only.
     *
     * @return array{ok:bool,message:string,result?:array<string,mixed>}
     */
    public function reconcile(string $caseKey, int $adminId): array
    {
        [$source, $id] = $this->parseCaseKey($caseKey);

        if ($source === 'didit') {
            $session = KycProviderSession::query()->findOrFail($id);
            $user = User::query()->findOrFail((int) $session->user_id);
            $before = (string) $session->normalized_status;
            $result = $this->didit->retrieveVerificationStatus($user, (string) $session->provider_session_id);
            $session->refresh();
            $after = (string) $session->normalized_status;

            $this->audit($adminId, 'reconcile_didit', (int) $user->id, 'didit', [
                'case_key' => $caseKey,
                'before' => $before,
                'after' => $after,
                'result_status' => $result['status'] ?? null,
            ]);

            return [
                'ok' => true,
                'message' => 'Didit reconciliation completed via Retrieve Session.',
                'result' => [
                    'status' => $result['status'] ?? $after,
                    'is_verified' => (bool) ($result['is_verified'] ?? false),
                    'before' => $before,
                    'after' => $after,
                ],
            ];
        }

        if ($source === 'sumsub') {
            $row = SumsubId::query()->findOrFail($id);
            $userId = (int) $row->user_id;
            try {
                $driver = app(SumSubDriver::class);
                $status = $driver->getStatus($userId);
            } catch (Throwable $e) {
                $this->audit($adminId, 'reconcile_sumsub_failed', $userId, 'sumsub', [
                    'case_key' => $caseKey,
                    'error_class' => $e::class,
                ]);

                return ['ok' => false, 'message' => 'SumSub status refresh failed (provider error).'];
            }

            $this->audit($adminId, 'reconcile_sumsub', $userId, 'sumsub', [
                'case_key' => $caseKey,
                'status_keys' => array_keys(is_array($status) ? $status : []),
            ]);

            return [
                'ok' => true,
                'message' => 'SumSub status refresh requested via existing driver.',
                'result' => [
                    'status' => is_array($status) ? ($status['status'] ?? $status['reviewStatus'] ?? 'ok') : 'ok',
                ],
            ];
        }

        return [
            'ok' => false,
            'message' => 'Manual verification cases are managed on the existing manual verification page. Provider reconcile is not applicable.',
        ];
    }

    public function openSessionUrl(string $caseKey, int $adminId): ?string
    {
        [$source, $id] = $this->parseCaseKey($caseKey);
        if ($source !== 'didit') {
            return null;
        }
        $session = KycProviderSession::query()->findOrFail($id);
        $url = (string) ($session->verification_url ?? '');
        if ($url === '' || ! str_starts_with($url, 'https://verify.didit.me/')) {
            return null;
        }
        $this->audit($adminId, 'open_provider_session', (int) $session->user_id, 'didit', [
            'case_key' => $caseKey,
            'host' => parse_url($url, PHP_URL_HOST),
        ]);

        return $url;
    }

    /**
     * @return array{filename:string,content:string}
     */
    public function exportAudit(string $caseKey, int $adminId): array
    {
        $detail = $this->caseDetail($caseKey);
        $userId = (int) $detail['identity']['user_id'];
        $logs = [];
        if (Schema::hasTable('kyc_logs')) {
            $logs = DB::table('kyc_logs')
                ->where('user_id', $userId)
                ->orderByDesc('id')
                ->limit(200)
                ->get(['id', 'provider', 'event', 'status', 'occurred_at', 'created_at'])
                ->map(fn ($r) => (array) $r)
                ->all();
        }

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'case_key' => $caseKey,
            'user_id' => $userId,
            'email_masked' => $detail['identity']['email_masked'],
            'timeline' => $detail['timeline'],
            'kyc_logs_meta' => $logs,
        ];

        $this->audit($adminId, 'export_audit', $userId, (string) $detail['provider']['current'], [
            'case_key' => $caseKey,
            'log_count' => count($logs),
        ]);

        return [
            'filename' => 'kyc-ops-'.$caseKey.'-'.now()->format('YmdHis').'.json',
            'content' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
        ];
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function casesUnionQuery(array $filters): Builder
    {
        $parts = [];

        if (Schema::hasTable('kyc_provider_sessions')) {
            $q = DB::table('kyc_provider_sessions as s')
                ->selectRaw("CONCAT('didit:', s.id) as case_key")
                ->addSelect(DB::raw("'didit' as source"))
                ->addSelect('s.id as source_id')
                ->addSelect('s.user_id')
                ->addSelect('s.provider')
                ->addSelect('s.normalized_status as status')
                ->addSelect('s.created_at')
                ->addSelect(DB::raw('COALESCE(s.last_synced_at, s.updated_at, s.created_at) as last_update'));
            $parts[] = $q;
        }

        if (Schema::hasTable('sumsub_ids')) {
            $q = DB::table('sumsub_ids as ss')
                ->selectRaw("CONCAT('sumsub:', ss.id) as case_key")
                ->addSelect(DB::raw("'sumsub' as source"))
                ->addSelect('ss.id as source_id')
                ->addSelect('ss.user_id')
                ->addSelect(DB::raw("CASE WHEN ss.provider IS NULL OR ss.provider = '' THEN 'sumsub' ELSE ss.provider END as provider"))
                ->addSelect(DB::raw("CASE WHEN ss.is_completed = 1 THEN 'approved' WHEN ss.status IS NULL OR ss.status = '' THEN 'pending' ELSE ss.status END as status"))
                ->addSelect('ss.created_at')
                ->addSelect(DB::raw('COALESCE(ss.updated_at, ss.created_at) as last_update'));
            $parts[] = $q;
        }

        if (Schema::hasTable('user_verification')) {
            $q = DB::table('user_verification as uv')
                ->selectRaw("CONCAT('manual:', uv.id) as case_key")
                ->addSelect(DB::raw("'manual' as source"))
                ->addSelect('uv.id as source_id')
                ->addSelect('uv.user_id')
                ->addSelect(DB::raw("'manual' as provider"))
                ->addSelect(DB::raw("CASE uv.status WHEN 1 THEN 'manual_approved' WHEN 2 THEN 'manual_declined' ELSE 'manual_pending' END as status"))
                ->addSelect('uv.created_at')
                ->addSelect(DB::raw('COALESCE(uv.updated_at, uv.created_at) as last_update'));
            $parts[] = $q;
        }

        if ($parts === []) {
            return DB::table(DB::raw('(SELECT NULL as case_key WHERE 1=0) as empty_cases'));
        }

        $union = array_shift($parts);
        foreach ($parts as $part) {
            $union->unionAll($part);
        }

        $wrapped = DB::query()->fromSub($union, 'u');

        if (! empty($filters['provider'])) {
            $wrapped->where('provider', (string) $filters['provider']);
        }
        if (! empty($filters['status'])) {
            $wrapped->where('status', (string) $filters['status']);
        }
        if (isset($filters['verified']) && $filters['verified'] !== '' && $filters['verified'] !== null) {
            $verified = (int) $filters['verified'] === 1;
            $ids = User::query()->where('is_verify_account', $verified ? 1 : 0)->pluck('id');
            $wrapped->whereIn('user_id', $ids);
        }
        if (! empty($filters['manual_only'])) {
            $wrapped->where('source', 'manual');
        }
        if (! empty($filters['date_from'])) {
            $wrapped->where('created_at', '>=', (string) $filters['date_from'].' 00:00:00');
        }
        if (! empty($filters['date_to'])) {
            $wrapped->where('created_at', '<=', (string) $filters['date_to'].' 23:59:59');
        }
        if (! empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            if (ctype_digit($q)) {
                $wrapped->where('user_id', (int) $q);
            } else {
                $userIds = User::query()
                    ->where('email', 'like', '%'.$q.'%')
                    ->limit(200)
                    ->pluck('id');
                $wrapped->whereIn('user_id', $userIds);
            }
        }
        if (! empty($filters['webhook_failures'])) {
            $failedUserIds = $this->usersWithWebhookFailures();
            $wrapped->whereIn('user_id', $failedUserIds);
        }
        if (! empty($filters['status_mismatch'])) {
            $mismatchIds = collect($this->statusMismatchItems(limit: 500))->pluck('user_id')->unique()->all();
            $wrapped->whereIn('user_id', $mismatchIds);
        }

        return $wrapped;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function timelineForUser(int $userId, string $source, int $sourceId): array
    {
        $events = [];

        if ($source === 'didit' && Schema::hasTable('kyc_provider_sessions')) {
            $session = KycProviderSession::query()->find($sourceId);
            if ($session) {
                $events[] = [
                    'at' => optional($session->created_at)?->toIso8601String(),
                    'label' => 'Session created',
                    'detail' => 'Provider session recorded',
                ];
                if ($session->started_at) {
                    $events[] = [
                        'at' => optional($session->started_at)?->toIso8601String(),
                        'label' => 'Verification started',
                        'detail' => null,
                    ];
                }
                if (in_array($session->normalized_status, ['pending', 'manual_review'], true)) {
                    $events[] = [
                        'at' => optional($session->last_synced_at ?? $session->updated_at)?->toIso8601String(),
                        'label' => 'Provider review / in progress',
                        'detail' => (string) $session->normalized_status,
                    ];
                }
                if ($session->completed_at || in_array($session->normalized_status, ['approved', 'declined', 'expired'], true)) {
                    $events[] = [
                        'at' => optional($session->completed_at ?? $session->last_synced_at)?->toIso8601String(),
                        'label' => 'Decision applied',
                        'detail' => (string) $session->normalized_status,
                    ];
                }
            }
        }

        if ($source === 'manual' && Schema::hasTable('user_verification')) {
            $row = UserVerification::query()->find($sourceId);
            if ($row) {
                $events[] = [
                    'at' => optional($row->created_at)?->toIso8601String(),
                    'label' => 'Manual documents submitted',
                    'detail' => null,
                ];
                $events[] = [
                    'at' => optional($row->updated_at)?->toIso8601String(),
                    'label' => match ((int) $row->status) {
                        1 => 'Manual approved',
                        2 => 'Manual declined',
                        default => 'Manual pending review',
                    },
                    'detail' => null,
                ];
            }
        }

        if (Schema::hasTable('kyc_logs')) {
            $logs = DB::table('kyc_logs')
                ->where('user_id', $userId)
                ->orderBy('id')
                ->limit(100)
                ->get(['event', 'status', 'provider', 'occurred_at', 'created_at']);
            foreach ($logs as $log) {
                $at = $log->occurred_at ?? $log->created_at ?? null;
                $events[] = [
                    'at' => $at !== null ? (string) $at : null,
                    'label' => $this->logEventLabel((string) $log->event, (string) $log->status),
                    'detail' => trim(($log->provider ?? '').' / '.($log->status ?? ''), ' /'),
                ];
            }
        }

        if (Schema::hasTable('provider_webhook_events')) {
            $hooks = DB::table('provider_webhook_events')
                ->where('provider', 'didit')
                ->orderByDesc('id')
                ->limit(50)
                ->get(['status', 'event_type', 'processed_at', 'created_at', 'event_id']);
            // Webhook table has no user FK; show recent Didit webhook operational events only on Didit cases.
            if ($source === 'didit') {
                foreach ($hooks->take(5) as $hook) {
                    $events[] = [
                        'at' => optional($hook->processed_at ?? $hook->created_at)?->toIso8601String(),
                        'label' => 'Webhook '.$hook->status,
                        'detail' => (string) ($hook->event_type ?? 'didit.kyc'),
                    ];
                }
            }
        }

        usort($events, static function ($a, $b) {
            return strcmp((string) ($a['at'] ?? ''), (string) ($b['at'] ?? ''));
        });

        return array_values($events);
    }

    /**
     * @return list<string>
     */
    private function safeActionsFor(string $source): array
    {
        return match ($source) {
            'didit' => ['reconcile', 'open_session', 'export_audit', 'copy_refs'],
            'sumsub' => ['reconcile', 'export_audit', 'copy_refs'],
            'manual' => ['export_audit', 'open_manual_queue'],
            default => ['export_audit'],
        };
    }

    private function webhookHealth(): array
    {
        if (! Schema::hasTable('provider_webhook_events')) {
            return [
                'available' => false,
                'last_delivery_at' => null,
                'last_accepted_at' => null,
                'processed' => 0,
                'failed' => 0,
                'duplicate_hint' => 0,
            ];
        }

        $base = DB::table('provider_webhook_events')->where('provider', 'didit');
        $last = (clone $base)->orderByDesc('id')->first();
        $lastAccepted = (clone $base)->where('status', 'processed')->orderByDesc('id')->first();
        $processed = (int) (clone $base)->where('status', 'processed')->count();
        $failed = (int) (clone $base)->whereIn('status', ['failed', 'rejected', 'invalid'])->count();

        return [
            'available' => true,
            'last_delivery_at' => optional($last->created_at ?? null)?->__toString(),
            'last_accepted_at' => optional($lastAccepted->processed_at ?? $lastAccepted->created_at ?? null)?->__toString(),
            'last_status' => $last->status ?? null,
            'signature_status' => 'fail_closed_v2',
            'processed' => $processed,
            'failed' => $failed,
            'duplicate_hint' => $processed > 0 ? 'idempotent_event_id' : 'none',
        ];
    }

    private function retrieveHealth(): array
    {
        $lastSync = null;
        if (Schema::hasTable('kyc_provider_sessions')) {
            $lastSync = KycProviderSession::query()
                ->whereNotNull('last_synced_at')
                ->orderByDesc('last_synced_at')
                ->value('last_synced_at');
        }

        $lastDecision = null;
        if (Schema::hasTable('kyc_logs')) {
            $lastDecision = DB::table('kyc_logs')
                ->where('event', 'decision')
                ->orderByDesc('id')
                ->value('occurred_at');
        }

        return [
            'last_reconciliation_at' => $lastSync ? (string) $lastSync : null,
            'last_sync_at' => $lastSync ? (string) $lastSync : null,
            'last_decision_log_at' => $lastDecision ? (string) $lastDecision : null,
            'last_failure' => null,
            'note' => 'Failures are fail-closed; Laravel warning logs are not exposed here.',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function statusMismatchItems(int $limit): array
    {
        if (! Schema::hasTable('kyc_provider_sessions')) {
            return [];
        }

        $rows = KycProviderSession::query()
            ->where('normalized_status', 'approved')
            ->whereHas('user', fn ($q) => $q->where('is_verify_account', 0))
            ->with('user:id,email,is_verify_account')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $rows->map(fn (KycProviderSession $s) => [
            'kind' => 'status_mismatch',
            'case_key' => 'didit:'.$s->id,
            'user_id' => (int) $s->user_id,
            'email_masked' => $this->maskEmail($s->user?->email),
            'provider' => 'didit',
            'detail' => 'Provider approved but account not verified locally',
            'detected_at' => optional($s->last_synced_at ?? $s->updated_at)?->toIso8601String(),
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pendingTooLongItems(int $limit): array
    {
        if (! Schema::hasTable('kyc_provider_sessions')) {
            return [];
        }
        $cutoff = now()->subHours(48);
        $rows = KycProviderSession::query()
            ->whereIn('normalized_status', ['not_started', 'pending', 'manual_review'])
            ->where('created_at', '<', $cutoff)
            ->with('user:id,email')
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        return $rows->map(fn (KycProviderSession $s) => [
            'kind' => 'pending_too_long',
            'case_key' => 'didit:'.$s->id,
            'user_id' => (int) $s->user_id,
            'email_masked' => $this->maskEmail($s->user?->email),
            'provider' => 'didit',
            'detail' => 'Pending/in-progress longer than 48h ('.$s->normalized_status.')',
            'detected_at' => optional($s->created_at)?->toIso8601String(),
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function duplicateSessionItems(int $limit): array
    {
        if (! Schema::hasTable('kyc_provider_sessions')) {
            return [];
        }
        $dupes = KycProviderSession::query()
            ->select('user_id', 'provider', DB::raw('COUNT(*) as c'))
            ->whereIn('normalized_status', ['not_started', 'pending', 'manual_review'])
            ->groupBy('user_id', 'provider')
            ->having('c', '>', 1)
            ->limit($limit)
            ->get();

        $out = [];
        foreach ($dupes as $d) {
            $user = User::query()->find((int) $d->user_id);
            $out[] = [
                'kind' => 'duplicate_session',
                'case_key' => null,
                'user_id' => (int) $d->user_id,
                'email_masked' => $this->maskEmail($user?->email),
                'provider' => (string) $d->provider,
                'detail' => 'Active session count='.(int) $d->c,
                'detected_at' => now()->toIso8601String(),
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function manualPendingItems(int $limit): array
    {
        if (! Schema::hasTable('user_verification')) {
            return [];
        }
        $rows = UserVerification::query()
            ->where('status', 0)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $rows->map(function (UserVerification $r) {
            $user = User::query()->find((int) $r->user_id);

            return [
                'kind' => 'manual_review_required',
                'case_key' => 'manual:'.$r->id,
                'user_id' => (int) $r->user_id,
                'email_masked' => $this->maskEmail($user?->email),
                'provider' => 'manual',
                'detail' => 'Manual upload awaiting admin review (existing manual flow)',
                'detected_at' => optional($r->created_at)?->toIso8601String(),
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function webhookFailureItems(int $limit): array
    {
        if (! Schema::hasTable('provider_webhook_events')) {
            return [];
        }
        $rows = DB::table('provider_webhook_events')
            ->where('provider', 'didit')
            ->whereIn('status', ['failed', 'rejected', 'invalid'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'kind' => 'webhook_rejected',
            'case_key' => null,
            'user_id' => null,
            'email_masked' => null,
            'provider' => 'didit',
            'detail' => 'Webhook delivery status='.$r->status.' (no user FK; investigate logs)',
            'detected_at' => (string) ($r->created_at ?? ''),
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mappingConflictItems(int $limit): array
    {
        if (! Schema::hasTable('kyc_logs')) {
            return [];
        }
        $rows = DB::table('kyc_logs')
            ->where('event', 'provider_mapping_conflict')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'kind' => 'failed_reconciliation',
            'case_key' => null,
            'user_id' => (int) $r->user_id,
            'email_masked' => $this->maskEmail(User::query()->where('id', (int) $r->user_id)->value('email')),
            'provider' => (string) ($r->provider ?? 'didit'),
            'detail' => 'Provider mapping conflict logged',
            'detected_at' => (string) ($r->occurred_at ?? $r->created_at ?? ''),
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentApprovals(int $limit): array
    {
        if (! Schema::hasTable('kyc_provider_sessions')) {
            return [];
        }

        return KycProviderSession::query()
            ->where('normalized_status', 'approved')
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (KycProviderSession $s) => [
                'case_key' => 'didit:'.$s->id,
                'user_id' => (int) $s->user_id,
                'at' => optional($s->completed_at ?? $s->last_synced_at)?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentFailures(int $limit): array
    {
        return array_slice(array_merge(
            $this->webhookFailureItems($limit),
            $this->mappingConflictItems($limit),
        ), 0, $limit);
    }

    /**
     * @param list<int> $userIds
     * @return array<int, array{at:?string,status:?string}>
     */
    private function lastWebhookByUser(array $userIds): array
    {
        // provider_webhook_events has no user_id; approximate via kyc_logs decision/webhook-ish events.
        $out = [];
        if (! Schema::hasTable('kyc_logs') || $userIds === []) {
            return $out;
        }
        $rows = DB::table('kyc_logs')
            ->whereIn('user_id', $userIds)
            ->whereIn('event', ['decision', 'session_created', 'provider_mapping_conflict'])
            ->orderByDesc('id')
            ->get(['user_id', 'event', 'status', 'occurred_at', 'created_at']);
        foreach ($rows as $row) {
            $uid = (int) $row->user_id;
            if (isset($out[$uid])) {
                continue;
            }
            $out[$uid] = [
                'at' => (string) ($row->occurred_at ?? $row->created_at ?? ''),
                'status' => (string) ($row->status ?? $row->event),
            ];
        }

        return $out;
    }

    /**
     * @return Collection<int, int>
     */
    private function usersWithWebhookFailures(): Collection
    {
        if (! Schema::hasTable('kyc_logs')) {
            return collect();
        }

        return DB::table('kyc_logs')
            ->where('event', 'provider_mapping_conflict')
            ->orderByDesc('id')
            ->limit(500)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function routingReason(User $user, string $resolved): string
    {
        if ((int) $user->is_verify_account === 1) {
            return 'Account already verified — no new provider session expected';
        }
        if (Schema::hasTable('user_verification')
            && UserVerification::query()->where('user_id', (int) $user->id)->where('status', 0)->exists()) {
            return 'Manual pending record — sticky manual path (resolver returns sumsub for UI branch)';
        }
        if (Schema::hasTable('kyc_provider_sessions')
            && KycProviderSession::query()
                ->where('user_id', (int) $user->id)
                ->where('provider', KycProviderResolver::PROVIDER_DIDIT)
                ->whereIn('normalized_status', ['not_started', 'pending', 'manual_review'])
                ->exists()) {
            return 'Sticky active Didit session';
        }
        if (Schema::hasTable('sumsub_ids')
            && SumsubId::query()
                ->where('user_id', (int) $user->id)
                ->where(function ($q) {
                    $q->whereNull('is_completed')->orWhere('is_completed', 0);
                })
                ->exists()) {
            return 'Sticky incomplete SumSub session';
        }
        if ($resolved === KycProviderResolver::PROVIDER_DIDIT) {
            return 'Configured default provider (KYC_PROVIDER=didit, empty allowlist)';
        }

        return 'Configured default / non-Didit resolution';
    }

    private function stageLabel(string $status, string $source): string
    {
        return match ($status) {
            'not_started' => 'Not started',
            'pending' => 'In progress',
            'manual_review' => 'Provider review',
            'approved' => 'Verified',
            'declined' => 'Declined',
            'expired' => 'Expired',
            'manual_pending' => 'Manual review',
            'manual_approved' => 'Manual approved',
            'manual_declined' => 'Manual declined',
            default => $source === 'manual' ? 'Manual' : ucfirst(str_replace('_', ' ', $status)),
        };
    }

    private function riskIndicator(string $status, bool $verified, bool $mismatch, ?string $webhookStatus): string
    {
        if ($mismatch) {
            return 'high';
        }
        if (in_array($webhookStatus, ['failed', 'rejected', 'invalid', 'conflict'], true)) {
            return 'high';
        }
        if (in_array($status, ['manual_pending', 'manual_review', 'pending'], true)) {
            return 'medium';
        }
        if ($verified || $status === 'approved' || $status === 'manual_approved') {
            return 'low';
        }

        return 'low';
    }

    private function logEventLabel(string $event, string $status): string
    {
        return match ($event) {
            'session_created' => 'Session created',
            'decision' => 'Decision applied ('.$status.')',
            'provider_mapping_conflict' => 'Mapping conflict',
            'admin_reconcile_didit', 'reconcile_didit' => 'Admin reconcile',
            default => $event !== '' ? $event : 'Event',
        };
    }

    /**
     * @return array{0:string,1:int}
     */
    private function parseCaseKey(string $caseKey): array
    {
        if (! preg_match('/^(didit|sumsub|manual):(\d+)$/', $caseKey, $m)) {
            throw new RuntimeException('Invalid case key');
        }

        return [$m[1], (int) $m[2]];
    }

    private function maskEmail(?string $email): string
    {
        $email = trim((string) $email);
        if ($email === '' || ! str_contains($email, '@')) {
            return '***';
        }
        [$local, $domain] = explode('@', $email, 2);
        $localMask = strlen($local) <= 1 ? '*' : ($local[0].'***');

        return $localMask.'@'.$domain;
    }

    private function maskSessionId(string $id): ?string
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }
        if (strlen($id) <= 8) {
            return '********';
        }

        return substr($id, 0, 4).'…'.substr($id, -4);
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function audit(int $adminId, string $action, int $userId, string $provider, array $meta = []): void
    {
        Log::info('kyc_ops_admin_action', [
            'admin_id' => $adminId,
            'action' => $action,
            'target_user_id' => $userId,
            'provider' => $provider,
            'meta' => $meta,
            'at' => now()->toIso8601String(),
        ]);

        if (! Schema::hasTable('kyc_logs')) {
            return;
        }

        try {
            DB::table('kyc_logs')->insert([
                'provider' => $provider,
                'event' => 'admin_'.$action,
                'status' => 'ok',
                'user_id' => $userId,
                'response_data' => json_encode([
                    'admin_id' => $adminId,
                    'meta' => $meta,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable) {
            // never break ops UI on audit write
        }
    }
}
