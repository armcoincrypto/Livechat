<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CompetitorLink;
use App\Models\CompetitorRates;
use iEXPackages\Courses\Services\CompetitorParserService;
use iEXPackages\Courses\Services\RatesLoggerService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Isolated bypass for competitor_rates import when protected Rates::fullUpdate() is broken.
 * Mirrors CompilerCompetitorService upsert behavior without touching ionCube code.
 */
final class CompilerCompetitorsOnlyCommand extends Command
{
    private const string LOCK_KEY = 'iex:competitor-rates:update:lock';

    private const int LOCK_TTL_SECONDS = 180;

    protected $signature = 'compiler:competitors-only
        {--link=Best : Competitor link name (competitor_links.name)}
        {--dry-run : Parse XML and report only; no DB writes}
        {--limit=0 : Max active pairs to process per link (0 = all)}
        {--no-history : Skip rates_history_logs writes}
        {--force : Run even if exchange is offline}';

    protected $description = 'Import competitor_rates from XML only, bypassing protected compiler:courses concurrency';

    public function handle(RatesLoggerService $logger): int
    {
        @ini_set('memory_limit', '512M');
        set_time_limit(300);

        if (!$this->option('force') && \App\Support\WorkStatusFresh::isOffline()) {
            $this->warn('Competitor rates not updated: exchange is offline (use --force to override).');

            return self::SUCCESS;
        }

        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_TTL_SECONDS);

        if (!$lock->get()) {
            $this->warn('Competitor rates update already running — skipping.');

            return self::SUCCESS;
        }

        $startedAt = microtime(true);
        $linkName = (string) $this->option('link');
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(0, (int) $this->option('limit'));
        $noHistory = (bool) $this->option('no-history');

        if ($noHistory) {
            $logger->setEnabledOverride(false);
        }

        Log::info('compiler:competitors-only start', [
            'link' => $linkName,
            'dry_run' => $dryRun,
            'limit' => $limit,
            'no_history' => $noHistory,
        ]);

        $this->comment(sprintf(
            'Starting competitor import%s for link [%s]...',
            $dryRun ? ' (dry-run)' : '',
            $linkName
        ));

        try {
            $links = CompetitorLink::query()
                ->where('status', 1)
                ->where('name', $linkName)
                ->orderBy('id')
                ->get();

            if ($links->isEmpty()) {
                $this->error("Active competitor link not found: {$linkName}");
                Log::error('compiler:competitors-only failed', [
                    'link' => $linkName,
                    'reason' => 'link_not_found',
                ]);

                return self::FAILURE;
            }

            $totals = [
                'matched' => 0,
                'missing' => 0,
                'skipped_inactive' => 0,
                'would_update' => 0,
                'updated' => 0,
                'errors' => 0,
            ];

            foreach ($links as $link) {
                $result = $this->processLink($link, $logger, $dryRun, $limit);

                foreach ($totals as $key => $value) {
                    $totals[$key] += $result[$key];
                }

                if ($result['fatal']) {
                    return self::FAILURE;
                }
            }

            $duration = round(microtime(true) - $startedAt, 4);

            $this->newLine();
            $this->info('Summary:');
            $this->line("  matched (in XML):     {$totals['matched']}");
            $this->line("  missing (not in XML): {$totals['missing']}");
            $this->line("  skipped (inactive):   {$totals['skipped_inactive']}");
            $this->line('  would-update:         ' . ($dryRun ? $totals['would_update'] : $totals['updated']));
            if (!$dryRun) {
                $this->line("  updated:              {$totals['updated']}");
            }
            $this->line("  errors:               {$totals['errors']}");
            $this->line("  duration:             {$duration} sec.");

            Log::info('compiler:competitors-only end', [
                'link' => $linkName,
                'dry_run' => $dryRun,
                'duration_sec' => $duration,
                'matched' => $totals['matched'],
                'missing' => $totals['missing'],
                'skipped_inactive' => $totals['skipped_inactive'],
                'would_update' => $totals['would_update'],
                'updated' => $totals['updated'],
                'errors' => $totals['errors'],
            ]);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $duration = round(microtime(true) - $startedAt, 4);

            $this->error('Fatal error: ' . $e->getMessage());

            Log::error('compiler:competitors-only failed', [
                'link' => $linkName,
                'dry_run' => $dryRun,
                'duration_sec' => $duration,
                'message' => $e->getMessage(),
            ]);

            return self::FAILURE;
        } finally {
            $this->releaseLock($lock);
        }
    }

    /**
     * @return array{
     *   matched:int,
     *   missing:int,
     *   skipped_inactive:int,
     *   would_update:int,
     *   updated:int,
     *   errors:int,
     *   fatal:bool
     * }
     */
    private function processLink(
        CompetitorLink $link,
        RatesLoggerService $logger,
        bool $dryRun,
        int $limit,
    ): array {
        $stats = [
            'matched' => 0,
            'missing' => 0,
            'skipped_inactive' => 0,
            'would_update' => 0,
            'updated' => 0,
            'errors' => 0,
            'fatal' => false,
        ];

        $this->newLine();
        $this->comment("Link: {$link->name} ({$link->link})");

        try {
            $parser = new CompetitorParserService((string) $link->link);
            $response = $parser->getAllRates();

            if ($response === []) {
                $this->error('XML fetch/parse returned no rates.');
                Log::error('compiler:competitors-only xml failure', [
                    'link' => $link->name,
                    'url' => $link->link,
                ]);
                $stats['fatal'] = true;

                return $stats;
            }

            $this->info('XML parsed: ' . count($response) . ' pair keys loaded.');

            $updates = [];
            $previewRows = [];
            $processedActive = 0;

            foreach ($link->rates as $rate) {
                if ((int) $rate->status !== 1) {
                    $stats['skipped_inactive']++;
                    continue;
                }

                if ($limit > 0 && $processedActive >= $limit) {
                    break;
                }

                $processedActive++;
                $key = Str::upper((string) $rate->name);

                if (!isset($response[$key])) {
                    $stats['missing']++;
                    Log::warning("Пропущено: нет курса для {$key}", [
                        'competitor' => $link->name,
                        'command' => 'compiler:competitors-only',
                    ]);
                    $this->line("  missing: {$key}");
                    continue;
                }

                $stats['matched']++;
                $newSumma = (float) $response[$key];
                $stats['would_update']++;

                $previewRows[] = [
                    'pair' => $key,
                    'old_summa' => $rate->summa ?? 'NULL',
                    'new_summa' => (string) $newSumma,
                ];

                if ($dryRun) {
                    continue;
                }

                $updates[] = [
                    'id' => $rate->id,
                    'value' => 1,
                    'summa' => $newSumma,
                    'updated_at' => now(),
                ];

                $logger->batchLog(
                    'competitor',
                    (int) $rate->id,
                    $key,
                    (string) $rate->summa,
                    (string) $response[$key],
                );
            }

            if ($previewRows !== []) {
                $this->table(['Pair', 'Old summa', 'New summa'], array_slice($previewRows, 0, max(1, $limit > 0 ? $limit : count($previewRows))));
            }

            if (!$dryRun && $updates !== []) {
                CompetitorRates::upsert($updates, ['id'], ['summa', 'updated_at']);
                $logger->flush();
                $stats['updated'] = count($updates);
                $this->info('Updated rows: ' . $stats['updated']);
            } elseif ($dryRun) {
                $this->info('Dry-run: no DB writes performed.');
            } else {
                $this->warn('No rows to update.');
            }
        } catch (Throwable $e) {
            $stats['errors']++;
            $this->error("Error for link [{$link->name}]: " . $e->getMessage());
            Log::error('compiler:competitors-only link error', [
                'competitor' => $link->name,
                'message' => $e->getMessage,
            ]);
        }

        return $stats;
    }

    private function releaseLock(Lock $lock): void
    {
        try {
            $lock->release();
        } catch (Throwable) {
            // Lock expires by TTL.
        }
    }
}
