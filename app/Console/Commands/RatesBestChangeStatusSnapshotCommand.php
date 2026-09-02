<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BestChangeDirection;
use App\Services\Rates\BestChangeBulkStatusGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Snapshot / dry-run restore for BestChange mapping status (not course_value).
 *
 * Recovery must re-enable status only; the compiler owns rates afterward.
 */
final class RatesBestChangeStatusSnapshotCommand extends Command
{
    protected $signature = 'rates:bestchange-status-snapshot
        {action=write : write|diff|restore-dry-run}
        {--file= : Snapshot JSON path (default storage/app/rates/bc-status-snapshots/latest.json)}
        {--confirm=0 : Required for restore-dry-run acknowledgment}';

    protected $description = 'Snapshot active BestChange mappings; dry-run status restore (never writes course_value).';

    public function handle(): int
    {
        $action = (string) $this->argument('action');
        $file = (string) ($this->option('file') ?: storage_path('app/rates/bc-status-snapshots/latest.json'));

        return match ($action) {
            'write' => $this->writeSnapshot($file),
            'diff' => $this->diffSnapshot($file),
            'restore-dry-run' => $this->restoreDryRun($file),
            default => $this->invalidAction($action),
        };
    }

    private function writeSnapshot(string $file): int
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            File::makeDirectory($dir, 0755, true);
        }
        $snap = BestChangeBulkStatusGuard::snapshotActiveMappings();
        File::put($file, json_encode($snap, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n");
        $this->info(sprintf('WROTE count=%d file=%s', $snap['count'], $file));

        return self::SUCCESS;
    }

    private function diffSnapshot(string $file): int
    {
        if (!is_file($file)) {
            $this->error('Snapshot missing: '.$file);

            return self::FAILURE;
        }
        $snap = json_decode((string) file_get_contents($file), true);
        if (!is_array($snap) || !isset($snap['rows']) || !is_array($snap['rows'])) {
            $this->error('Invalid snapshot JSON');

            return self::FAILURE;
        }

        $snapIds = [];
        foreach ($snap['rows'] as $row) {
            $snapIds[(int) ($row['bc_id'] ?? 0)] = $row;
        }
        unset($snapIds[0]);

        $live = BestChangeDirection::query()
            ->whereIn('id', array_keys($snapIds))
            ->get(['id', 'status', 'position_num'])
            ->keyBy('id');

        $wouldEnable = [];
        $alreadyOn = 0;
        $missing = 0;
        foreach ($snapIds as $id => $row) {
            $liveRow = $live->get($id);
            if ($liveRow === null) {
                $missing++;
                continue;
            }
            if ((int) $liveRow->status === 1) {
                $alreadyOn++;
            } else {
                $wouldEnable[] = $id;
            }
        }

        $payload = [
            'snapshot_count' => count($snapIds),
            'already_status_1' => $alreadyOn,
            'would_enable_status_1' => count($wouldEnable),
            'missing_rows' => $missing,
            'sample_enable_ids' => array_slice($wouldEnable, 0, 20),
            'note' => 'restore writes status only; compiler must refresh rate_value/course_value',
        ];
        $this->line(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }

    private function restoreDryRun(string $file): int
    {
        if (!(bool) $this->option('confirm')) {
            $this->error('Pass --confirm=1 to acknowledge dry-run restore plan (still no writes).');

            return self::FAILURE;
        }

        $this->warn('DRY-RUN only: no DB writes. Use a separate approved ops procedure for live restore.');

        return $this->diffSnapshot($file);
    }

    private function invalidAction(string $action): int
    {
        $this->error('Unknown action: '.$action.' (write|diff|restore-dry-run)');

        return self::FAILURE;
    }
}
