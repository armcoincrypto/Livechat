<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rates\DerivedMarketBaselineAuthority;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Production enablement for crypto → card/fiat rails:
 * currency visibility unlock, course restore, BestChange-ready enable,
 * then derived-baseline apply for no-market pairs.
 */
final class DirectionsEnableCardFiatRailsCommand extends Command
{
    protected $signature = 'directions:enable-card-fiat-rails
        {--dry-run : snapshot + plan only}
        {--apply : apply visibility, restores, and derived baselines}
        {--skip-derived : do not run derived-baseline refresh}
        {--plan= : path to card-fiat-rails-enable-plan.json}';

    protected $description = 'Enable crypto→card/fiat rails (visibility + restore + derived baselines).';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $dry = (bool) $this->option('dry-run') || !$apply;
        $planPath = (string) ($this->option('plan') ?: base_path('resources/rates/card-fiat-rails-enable-plan.json'));
        if (!is_file($planPath)) {
            $this->error('plan_missing ' . $planPath);

            return self::FAILURE;
        }
        $plan = json_decode((string) file_get_contents($planPath), true);
        if (!is_array($plan)) {
            $this->error('plan_invalid');

            return self::FAILURE;
        }

        $snapDir = storage_path('app/card-fiat-rails-snapshots');
        if (!is_dir($snapDir)) {
            File::makeDirectory($snapDir, 0755, true);
        }
        $stamp = gmdate('Ymd\THis\Z');
        $snapshot = [
            'at' => $stamp,
            'currencies' => DB::table('currencies')
                ->whereIn('designation_xml', array_values(array_unique(array_merge(
                    $plan['vis_dest'] ?? [],
                    $plan['vis_src'] ?? [],
                ))))
                ->get(['id', 'designation_xml', 'status', 'visible_give', 'visible_receiving'])
                ->map(static fn ($r) => (array) $r)
                ->all(),
            'directions' => DB::table('direction_exchange')
                ->whereIn('id', array_values(array_unique(array_merge(
                    array_column($plan['restore_course'] ?? [], 'id'),
                    array_column($plan['bc_ready'] ?? [], 'id'),
                    $plan['derived_ids'] ?? [],
                    $plan['sticky_protect'] ?? [],
                ))))
                ->get(['id', 'status', 'allow_export', 'course_value', 'manual_rate_value', 'parser_source_name', 'is_error_rate'])
                ->map(static fn ($r) => (array) $r)
                ->all(),
        ];
        $snapFile = $snapDir . '/snapshot-' . $stamp . '.json';
        file_put_contents($snapFile, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $report = [
            'mode' => $dry ? 'dry-run' : 'apply',
            'snapshot' => $snapFile,
            'visibility' => [],
            'restore_course' => [],
            'bc_ready' => [],
            'derived' => null,
            'sticky_ok' => true,
        ];

        foreach ($plan['vis_dest'] ?? [] as $code) {
            $report['visibility'][] = $this->unlockCurrency((string) $code, receiving: true, giving: true, dry: $dry);
        }
        foreach ($plan['vis_src'] ?? [] as $code) {
            $report['visibility'][] = $this->unlockCurrency((string) $code, receiving: false, giving: true, dry: $dry);
        }

        $sticky = array_map('intval', $plan['sticky_protect'] ?? [1274, 1275]);
        foreach ($plan['restore_course'] ?? [] as $row) {
            $id = (int) $row['id'];
            if (in_array($id, $sticky, true)) {
                continue;
            }
            $report['restore_course'][] = $this->enableDirection(
                $id,
                course: (string) $row['course'],
                export: (int) (($row['export'] ?? 0) === 2 ? 0 : ($row['export'] ?? 0)),
                dry: $dry,
            );
        }
        foreach ($plan['bc_ready'] ?? [] as $row) {
            $id = (int) $row['id'];
            if (in_array($id, $sticky, true)) {
                continue;
            }
            $course = (string) ($row['bc'] ?? $row['course'] ?? '0');
            $report['bc_ready'][] = $this->enableDirection($id, course: $course, export: 0, dry: $dry);
        }

        // Protect sticky manuals
        foreach ($sticky as $id) {
            $d = DB::table('direction_exchange')->where('id', $id)->first();
            if ($d && (string) $d->parser_source_name !== 'Ручной курс') {
                $report['sticky_ok'] = false;
            }
        }

        if (!(bool) $this->option('skip-derived')) {
            $auth = DerivedMarketBaselineAuthority::fromStorageApp();
            $report['derived'] = $auth->refreshAll(dryRun: $dry);
        }

        $zeros = (int) DB::table('direction_exchange')
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->where('course_value', '<=', 0)
            ->count();
        $report['enabled_zero_after'] = $zeros;

        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if ($zeros > 0) {
            return self::FAILURE;
        }
        if (!$report['sticky_ok']) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string,mixed>
     */
    private function unlockCurrency(string $code, bool $receiving, bool $giving, bool $dry): array
    {
        $q = DB::table('currencies')->where('designation_xml', $code);
        $before = $q->get(['id', 'visible_give', 'visible_receiving'])->map(static fn ($r) => (array) $r)->all();
        $update = [];
        if ($giving) {
            $update['visible_give'] = 1;
        }
        if ($receiving) {
            $update['visible_receiving'] = 1;
        }
        if (!$dry && $update !== []) {
            DB::table('currencies')->where('designation_xml', $code)->update($update);
        }

        return ['code' => $code, 'before' => $before, 'update' => $update, 'dry_run' => $dry];
    }

    /**
     * @return array<string,mixed>
     */
    private function enableDirection(int $id, string $course, int $export, bool $dry): array
    {
        $before = DB::table('direction_exchange')->where('id', $id)->first();
        if (!$before) {
            return ['id' => $id, 'ok' => false, 'reason' => 'missing'];
        }
        if ((float) $course <= 0) {
            return ['id' => $id, 'ok' => false, 'reason' => 'non_positive_course'];
        }
        $write = [
            'status' => 1,
            'allow_export' => $export === 2 ? 0 : $export,
            'course_value' => $course,
            'is_error_rate' => 0,
            'error_rate_text' => null,
            'updated_at' => now()->toDateTimeString(),
        ];
        // Keep existing manual/parser source when already set; otherwise leave as-is.
        if (!$dry) {
            DB::table('direction_exchange')->where('id', $id)->update($write);
        }

        return [
            'id' => $id,
            'ok' => true,
            'before' => [
                'status' => (int) $before->status,
                'allow_export' => (int) $before->allow_export,
                'course' => $before->course_value,
            ],
            'write' => $write,
            'dry_run' => $dry,
        ];
    }
}
