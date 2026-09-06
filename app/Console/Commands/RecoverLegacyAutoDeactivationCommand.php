<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Auth\LegacyAutoDeactivationRecoveryService;
use Illuminate\Console\Command;

/**
 * Controlled recovery for accounts deactivated by the old unverified cleanup.
 *
 * Usage:
 *   php artisan users:recover-legacy-auto-deactivation --dry-run --cohort=B,C
 *   php artisan users:recover-legacy-auto-deactivation --apply --cohort=B --limit=200
 *   php artisan users:recover-legacy-auto-deactivation --apply --cohort=C --limit=200 --offset=0
 */
class RecoverLegacyAutoDeactivationCommand extends Command
{
    protected $signature = 'users:recover-legacy-auto-deactivation
        {--dry-run : Classify and report without mutating}
        {--apply : Apply deactivation=0 for eligible accounts}
        {--cohort=B,C : Comma-separated cohorts to process (B and/or C only)}
        {--limit=200 : Max accounts to process in this invocation}
        {--offset=0 : Offset within the selected cohort ID list}
        {--classify-only : Only rebuild classification artifacts}';

    protected $description = 'Recover historical accounts deactivated by old unverified auto-cleanup (B/C only)';

    public function handle(LegacyAutoDeactivationRecoveryService $recovery): int
    {
        $apply = (bool) $this->option('apply');
        $dryRun = (bool) $this->option('dry-run') || ! $apply;
        if ($apply && $this->option('dry-run')) {
            $this->error('Refuse both --apply and --dry-run.');
            return self::FAILURE;
        }

        $cleanupDays = (int) iEXSetting('new_user_registration_cleanup_days');
        if ($cleanupDays <= 0) {
            $this->error('new_user_registration_cleanup_days is not configured.');
            return self::FAILURE;
        }

        $this->info('CLASSIFY begin cleanup_days='.$cleanupDays);
        $classified = $recovery->classify($cleanupDays);
        $dir = $recovery->writeClassificationArtifacts($classified);

        $this->line('COHORT_A='.count($classified['A']));
        $this->line('COHORT_B='.count($classified['B']));
        $this->line('COHORT_C='.count($classified['C']));
        $this->line('COHORT_D='.count($classified['D']));
        $this->line('ARTIFACT_DIR='.$dir);
        $this->line('AUTO_CLEANUP_CONFIRMED='.$classified['meta']['origin_auto']);
        $this->line('MANUAL_OR_SECURITY_DEACTIVATION='.$classified['meta']['origin_security']);
        $this->line('ORIGIN_UNKNOWN='.$classified['meta']['origin_unknown']);

        if ($this->option('classify-only')) {
            $this->info('CLASSIFY_ONLY complete');
            return self::SUCCESS;
        }

        $cohortOpt = strtoupper((string) $this->option('cohort'));
        $cohorts = array_values(array_filter(array_map('trim', explode(',', $cohortOpt))));
        foreach ($cohorts as $c) {
            if (! in_array($c, ['B', 'C'], true)) {
                $this->error("Invalid cohort {$c}; only B,C allowed for recovery.");
                return self::FAILURE;
            }
        }

        $limit = max(1, (int) $this->option('limit'));
        $offset = max(0, (int) $this->option('offset'));

        // Process each cohort independently with the same offset/limit (safer batching).
        $selected = [];
        foreach ($cohorts as $c) {
            foreach (array_slice($classified[$c], $offset, $limit) as $id) {
                $selected[] = ['id' => (int) $id, 'cohort' => $c];
            }
        }

        $this->line('WOULD_REACTIVATE_COUNT='.count($selected));
        $this->line('MODE='.($apply ? 'APPLY' : 'DRY_RUN'));
        $this->line('LIMIT='.$limit.' OFFSET='.$offset.' COHORTS='.implode(',', $cohorts));

        if ($selected === []) {
            $this->warn('No IDs selected for this slice.');
            return self::SUCCESS;
        }

        // Snapshot before mutation
        $stamp = gmdate('Ymd\\THis\\Z');
        $snapshotPath = $dir."/snapshot_before_{$stamp}.json";
        $rows = \App\Models\User::query()
            ->whereIn('id', array_column($selected, 'id'))
            ->get(['id', 'deactivation', 'email_verified_at', 'banned_at', 'updated_at']);
        file_put_contents($snapshotPath, $rows->toJson(JSON_PRETTY_PRINT));
        @chmod($snapshotPath, 0640);
        $this->line('TARGET_USER_IDS_SNAPSHOT='.$snapshotPath);

        $allReactivated = [];
        $allSkipped = [];
        foreach ($cohorts as $c) {
            $ids = array_slice($classified[$c], $offset, $limit);
            $result = $recovery->reactivateIds($ids, $c, $cleanupDays, $apply);
            $allReactivated = array_merge($allReactivated, $result['reactivated']);
            $allSkipped = array_merge($allSkipped, $result['skipped']);
        }

        $auditPath = $dir.'/audit_'.($apply ? 'apply' : 'dryrun')."_{$stamp}.json";
        file_put_contents($auditPath, json_encode([
            'mode' => $apply ? 'apply' : 'dry_run',
            'version' => LegacyAutoDeactivationRecoveryService::VERSION,
            'limit' => $limit,
            'offset' => $offset,
            'cohorts' => $cohorts,
            'reactivated' => $allReactivated,
            'skipped' => $allSkipped,
        ], JSON_PRETTY_PRINT));
        @chmod($auditPath, 0640);

        $this->info('REACTIVATED_COUNT='.count($allReactivated));
        $this->info('SKIPPED_COUNT='.count($allSkipped));
        $this->line('AUDIT_PATH='.$auditPath);

        if ($apply) {
            $this->warn('EMAIL_VERIFICATION_MASS_CHANGED=0 (email_verified_at untouched)');
            $this->warn('PASSWORDS_CHANGED=0');
        }

        return self::SUCCESS;
    }
}
