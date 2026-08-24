<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rates\DerivedMarketBaselineAuthority;
use Illuminate\Console\Command;

final class DirectionsDerivedBaselineRefreshCommand extends Command
{
    protected $signature = 'directions:derived-baseline-refresh
        {--dry-run : show actions without writing}
        {--apply : apply derived baseline ownership}
        {--full : dump full JSON results}
        {--ids= : comma-separated owned direction IDs to refresh}';

    protected $description = 'Refresh DERIVED_MARKET_BASELINE owned directions (e.g. 1249 GRAM→CARDKZT).';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $auth = DerivedMarketBaselineAuthority::fromStorageApp();
        $onlyIds = $this->parseIds((string) $this->option('ids'));
        $results = $auth->refreshAll(dryRun: !$apply, onlyIds: $onlyIds);

        $summary = $this->summarize($results, $apply);
        $this->line(json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        if ((bool) $this->option('full')) {
            $this->line(json_encode([
                'mode' => $apply ? 'apply' : 'dry-run',
                'results' => $results,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        $owned = (int) $summary['owned'];
        $failed = (int) $summary['failed'];
        if ($owned > 0 && $failed === $owned) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param array<int,array<string,mixed>> $results
     * @return array<string,mixed>
     */
    private function summarize(array $results, bool $apply): array
    {
        $owned = 0;
        $failed = 0;
        $skipped = [];
        $wouldWrite = [];
        $largeDeltas = [];
        $capped = [];

        foreach ($results as $r) {
            $reason = (string) ($r['eval']['reason'] ?? ($r['skipped'] ?? ''));
            if ($reason === 'not_owned') {
                continue;
            }
            $owned++;
            if (empty($r['eval']['ok']) && empty($r['write']['course_value'])) {
                $failed++;
            }
            $dirId = (int) ($r['direction_id'] ?? 0);
            $row = [
                'direction_id' => $dirId,
                'pair' => $r['pair'] ?? null,
                'old_base' => $r['old_base'] ?? null,
                'new_base' => $r['new_base'] ?? ($r['write']['course_value'] ?? null),
                'delta_pct' => $r['delta_pct'] ?? null,
                'skipped' => $r['skipped'] ?? null,
                'legs' => $r['legs'] ?? ($r['eval']['components'] ?? null),
            ];
            if (!empty($r['skipped']) || !empty($r['skipped_delta_cap'])) {
                $skipped[] = $row;
                if (!empty($r['skipped_delta_cap'])) {
                    $capped[] = $row;
                }
                continue;
            }
            $delta = $r['delta_pct'] ?? null;
            if (is_numeric($delta) && abs((float) $delta) >= 5.0) {
                $largeDeltas[] = $row;
            }
            if (isset($r['write']['course_value'])) {
                $wouldWrite[] = $row;
            }
        }

        return [
            'mode' => $apply ? 'apply' : 'dry-run',
            'owned' => $owned,
            'failed' => $failed,
            'would_write' => count($wouldWrite),
            'skipped' => count($skipped),
            'large_delta_ge_5pct' => $largeDeltas,
            'delta_safety_capped' => $capped,
            'skipped_rows' => $skipped,
            'sample_writes' => array_slice($wouldWrite, 0, 25),
        ];
    }

    /**
     * @return list<int>|null  null = all owned IDs
     */
    private function parseIds(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $ids = [];
        foreach (explode(',', $raw) as $part) {
            $part = trim($part);
            if ($part === '' || !ctype_digit($part)) {
                continue;
            }
            $ids[] = (int) $part;
        }

        return $ids;
    }
}
