<?php
declare(strict_types=1);

namespace iEXPackages\Proxy\Console;

use iEXPackages\Proxy\Models\ProxyHealthLog;
use Illuminate\Console\Command;

final class ProxyPruneHealthLogsCommand extends Command
{
    protected $signature = 'proxy:prune-health-logs {--days=30 : Delete logs older than N days}';
    protected $description = 'Prune proxy_health_logs older than N days';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $before = now()->subDays($days);

        $count = ProxyHealthLog::query()
            ->where('created_at', '<', $before)
            ->count();

        if ($count === 0) {
            $this->info("No proxy health logs to prune (older than {$days} days).");
            return self::SUCCESS;
        }

        ProxyHealthLog::query()
            ->where('created_at', '<', $before)
            ->delete();

        $this->info("Pruned {$count} proxy health logs (older than {$days} days).");

        return self::SUCCESS;
    }
}
