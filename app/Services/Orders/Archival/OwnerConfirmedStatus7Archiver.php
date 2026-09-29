<?php

declare(strict_types=1);

namespace App\Services\Orders\Archival;

use App\Models\Task;
use Illuminate\Support\Facades\DB;

/**
 * Archives status=7 backlog under owner attestation.
 * Preserves status=7. Mutates only is_archive / archived_at / opt_params.
 */
final class OwnerConfirmedStatus7Archiver
{
    public const EXPECTED_TARGET = 340;

    /**
     * @return list<array<string,mixed>>
     */
    public function previewCandidates(): array
    {
        return $this->candidateQuery()
            ->orderBy('id')
            ->get([
                'id',
                'public_id',
                'status',
                'created_at',
                'started_at',
                'updated_at',
                'completed_at',
                'is_archive',
                'archived_at',
                'opt_params',
            ])
            ->map(fn (Task $t) => $this->manifestRow($t))
            ->all();
    }

    /**
     * @return array{
     *   target_count:int,
     *   newly_archived:int,
     *   already_archived:int,
     *   unexpected_rows:int,
     *   status_changed:int,
     *   orders_deleted:int,
     *   dry_run:bool,
     *   manifest_sha256:?string,
     *   rows:list<array<string,mixed>>
     * }
     */
    public function run(
        bool $apply,
        string $resolvedBy,
        ?string $manifestWritePath = null,
        bool $allowCountMismatch = false
    ): array {
        $rows = $this->previewCandidates();
        $already = 0;
        $toApply = [];
        foreach ($rows as $row) {
            if ($row['already_resolved']) {
                $already++;
            } else {
                $toApply[] = $row;
            }
        }

        $manifestSha = null;
        if ($manifestWritePath !== null && $manifestWritePath !== '') {
            $json = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
            if (file_put_contents($manifestWritePath, $json) === false) {
                throw new \RuntimeException('Cannot write manifest: '.$manifestWritePath);
            }
            $manifestSha = hash('sha256', $json);
            file_put_contents($manifestWritePath.'.sha256', $manifestSha."\n");
        }

        $unexpected = count($rows) === self::EXPECTED_TARGET ? 0 : abs(count($rows) - self::EXPECTED_TARGET);

        if (!$apply) {
            return [
                'target_count' => count($rows),
                'newly_archived' => count($toApply),
                'already_archived' => $already,
                'unexpected_rows' => $unexpected,
                'status_changed' => 0,
                'orders_deleted' => 0,
                'dry_run' => true,
                'manifest_sha256' => $manifestSha,
                'rows' => $rows,
            ];
        }

        if ($unexpected !== 0 && !$allowCountMismatch) {
            throw new \RuntimeException(sprintf(
                'REFUSED: candidate count %d != expected %d (unexpected_delta=%d). Re-inspect before apply.',
                count($rows),
                self::EXPECTED_TARGET,
                $unexpected
            ));
        }

        $newly = 0;
        $resolvedAt = now()->toIso8601String();

        DB::transaction(function () use ($toApply, $resolvedBy, $resolvedAt, &$newly) {
            foreach (array_chunk($toApply, 50) as $chunk) {
                foreach ($chunk as $row) {
                    /** @var Task $task */
                    $task = Task::query()->whereKey($row['order_id'])->lockForUpdate()->first();
                    if ($task === null) {
                        throw new \RuntimeException('Missing task '.$row['order_id']);
                    }
                    if ((int) $task->status !== 7) {
                        throw new \RuntimeException('Status drift on task '.$task->id.'; refuse.');
                    }
                    if (OwnerConfirmedHistoricalSettlement::isResolved(
                        is_array($task->opt_params) ? $task->opt_params : null
                    )) {
                        continue;
                    }

                    $opt = is_array($task->opt_params) ? $task->opt_params : [];
                    $opt[OwnerConfirmedHistoricalSettlement::OPT_KEY] = OwnerConfirmedHistoricalSettlement::buildPayload(
                        (int) $task->id,
                        $resolvedBy,
                        $resolvedAt
                    );

                    $task->forceFill([
                        'is_archive' => 1,
                        'archived_at' => $task->archived_at ?? now(),
                        'opt_params' => $opt,
                    ]);
                    // Never touch status / balances / payouts.
                    $task->save();
                    $newly++;
                }
            }
        });

        // Re-count already for idempotent reporting
        $after = $this->previewCandidates();
        $alreadyAfter = count(array_filter($after, fn ($r) => $r['already_resolved']));

        return [
            'target_count' => count($after),
            'newly_archived' => $newly,
            'already_archived' => $alreadyAfter,
            'unexpected_rows' => count($after) === self::EXPECTED_TARGET ? 0 : abs(count($after) - self::EXPECTED_TARGET),
            'status_changed' => 0,
            'orders_deleted' => 0,
            'dry_run' => false,
            'manifest_sha256' => $manifestSha,
            'rows' => $after,
        ];
    }

    private function candidateQuery()
    {
        // All live status=7 rows (including already archived under this attestation)
        // so idempotent re-runs report ALREADY_ARCHIVED correctly.
        return Task::query()->where('status', 7)->whereNull('deleted_at');
    }

    /**
     * @return array<string,mixed>
     */
    private function manifestRow(Task $t): array
    {
        $opt = is_array($t->opt_params) ? $t->opt_params : null;
        $res = OwnerConfirmedHistoricalSettlement::fromOptParams($opt);
        $already = $res !== null;

        return [
            'order_id' => (int) $t->id,
            'public_id' => $t->public_id,
            'current_status' => (int) $t->status,
            'created_at' => (string) $t->created_at,
            'paid_at' => $t->started_at ? (string) $t->started_at : null,
            'payment_reference' => null,
            'current_resolution_state' => $already
                ? (string) ($res['resolution_type'] ?? 'RESOLVED')
                : ((int) $t->is_archive === 1 ? 'ARCHIVED_WITHOUT_OWNER_ATTESTATION' : 'UNRESOLVED'),
            'proposed_resolution_state' => OwnerConfirmedHistoricalSettlement::RESOLUTION_TYPE,
            'proposed_reference' => OwnerConfirmedHistoricalSettlement::referenceForOrderId((int) $t->id),
            'already_resolved' => $already,
            'is_archive' => (int) $t->is_archive,
            'archived_at' => $t->archived_at ? (string) $t->archived_at : null,
        ];
    }
}
