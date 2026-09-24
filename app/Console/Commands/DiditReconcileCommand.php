<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\KycProviderSession;
use App\Models\ProviderWebhookEvent;
use iEXPackages\KYCPlugin\Services\KycProviderResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only Didit KYC reconciliation inventory (default --dry-run).
 *
 * Classifies unresolved Didit webhook rows and session status inventory.
 * Never mutates KYC state, never creates sessions, never fakes approvals.
 */
final class DiditReconcileCommand extends Command
{
    protected $signature = 'didit:reconcile
        {--dry-run : Inventory only (default; ignore --apply)}
        {--apply : Forbidden in Wave 4 — refused}
        {--format=json : json|table}';

    protected $description = 'Classify Didit KYC sessions and unresolved webhooks (dry-run by default).';

    public function handle(): int
    {
        if ($this->option('apply') && ! $this->option('dry-run')) {
            $this->error('APPLY_REFUSED=didit:reconcile is inventory-only; no state mutation');

            return self::FAILURE;
        }

        if (! Schema::hasTable('kyc_provider_sessions') || ! Schema::hasTable('provider_webhook_events')) {
            $this->error('REQUIRED_TABLES_MISSING');

            return self::FAILURE;
        }

        $provider = KycProviderResolver::PROVIDER_DIDIT;

        $byStatus = KycProviderSession::query()
            ->where('provider', $provider)
            ->selectRaw('normalized_status as st, COUNT(*) as c')
            ->groupBy('st')
            ->pluck('c', 'st')
            ->all();

        $sessions = [
            'TOTAL' => (int) array_sum($byStatus),
            'APPROVED' => (int) ($byStatus['approved'] ?? 0),
            'PENDING' => (int) (($byStatus['pending'] ?? 0) + ($byStatus['not_started'] ?? 0) + ($byStatus['manual_review'] ?? 0)),
            'IN_PROGRESS' => (int) (($byStatus['pending'] ?? 0) + ($byStatus['not_started'] ?? 0) + ($byStatus['manual_review'] ?? 0)),
            'DECLINED' => (int) (($byStatus['rejected'] ?? 0)),
            'EXPIRED' => (int) ($byStatus['expired'] ?? 0),
            'ABANDONED' => (int) ($byStatus['abandoned'] ?? 0),
            'ERROR' => (int) ($byStatus['unknown'] ?? 0),
            'BY_STATUS' => $byStatus,
        ];

        $unresolved = ProviderWebhookEvent::query()
            ->where('provider', $provider)
            ->where('status', ProviderWebhookEvent::STATUS_UNRESOLVED)
            ->orderBy('id')
            ->get(['id', 'event_id', 'event_type', 'provider_payment_id', 'created_at']);

        $classCounts = [];
        $rows = [];
        foreach ($unresolved as $event) {
            $class = self::classifyUnresolved($event);
            $classCounts[$class] = ($classCounts[$class] ?? 0) + 1;
            $rows[] = [
                'id' => (int) $event->id,
                'event_type' => (string) $event->event_type,
                'class' => $class,
                'created_at' => (string) $event->created_at,
            ];
        }

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'mode' => 'dry-run',
            'provider' => $provider,
            'sessions' => $sessions,
            'unresolved_total' => $unresolved->count(),
            'unresolved_by_class' => $classCounts,
            'WOULD_UPDATE' => 0,
            'WOULD_SKIP' => $unresolved->count(),
            'ALREADY_CONSISTENT' => (int) ($sessions['APPROVED'] ?? 0),
            'AMBIGUOUS' => (int) ($classCounts['UNKNOWN'] ?? 0),
            'REQUIRES_PROVIDER_LOOKUP' => (int) (($classCounts['UNKNOWN_SESSION'] ?? 0) + ($classCounts['STALE_HISTORICAL_UNKNOWN_SESSION'] ?? 0)),
            'UNEXPLAINED_UNRESOLVED' => (int) ($classCounts['UNKNOWN'] ?? 0),
            'sample' => array_slice($rows, 0, 25),
            'note' => 'No KYC state mutation. Historical unresolved rows are classified only.',
        ];

        if ($this->option('format') === 'table') {
            $this->info('sessions TOTAL='.$sessions['TOTAL'].' approved='.$sessions['APPROVED'].' expired='.$sessions['EXPIRED']);
            $this->info('unresolved_total='.$payload['unresolved_total']);
            foreach ($classCounts as $k => $v) {
                $this->line("  {$k}={$v}");
            }

            return self::SUCCESS;
        }

        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }

    /**
     * @param  object{event_id?:string,provider_payment_id?:string,created_at?:mixed}  $event
     */
    public static function classifyUnresolved(object $event): string
    {
        $paymentId = trim((string) ($event->provider_payment_id ?? ''));
        $eventId = trim((string) ($event->event_id ?? ''));

        $session = null;
        if ($paymentId !== '') {
            $session = KycProviderSession::query()
                ->where('provider', KycProviderResolver::PROVIDER_DIDIT)
                ->where(function ($q) use ($paymentId) {
                    $q->where('provider_session_id', $paymentId)
                        ->orWhere('provider_reference', $paymentId);
                })
                ->first();
        }
        if ($session === null && $eventId !== '') {
            $session = KycProviderSession::query()
                ->where('provider', KycProviderResolver::PROVIDER_DIDIT)
                ->where('last_event_id', $eventId)
                ->first();
        }

        $ageDays = null;
        if (! empty($event->created_at)) {
            $ts = strtotime((string) $event->created_at);
            if ($ts !== false) {
                $ageDays = (int) floor((time() - $ts) / 86400);
            }
        }

        if ($session === null) {
            return ($ageDays !== null && $ageDays > 14)
                ? 'STALE_HISTORICAL_UNKNOWN_SESSION'
                : 'UNKNOWN_SESSION';
        }

        $ns = (string) ($session->normalized_status ?? '');
        $base = match (true) {
            in_array($ns, ['approved', 'rejected', 'expired', 'abandoned'], true) => 'ALREADY_APPLIED',
            in_array($ns, ['pending', 'not_started', 'manual_review'], true) => 'ORDERING_RACE',
            default => 'KNOWN_SESSION',
        };

        if ($ageDays !== null && $ageDays > 14) {
            return 'STALE_HISTORICAL_'.$base;
        }

        return $base;
    }
}
