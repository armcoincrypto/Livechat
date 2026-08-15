<?php
declare(strict_types=1);

namespace iEXPackages\AuthAudit\Console;

use iEXPackages\AuthAudit\Jobs\EnrichAuditEventJob;
use iEXPackages\AuthAudit\Models\AuthEvent;
use Illuminate\Console\Command;

final class ReenrichAuthAuditCommand extends Command
{
    protected $signature = 'auth-audit:reenrich {--days=7 : За сколько дней назад брать события} {--queue=low : Очередь}';
    protected $description = 'Переобогащает auth_audit_events (GeoIP/context) за указанный период';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $queue = (string) $this->option('queue');

        $from = now()->subDays(max(1, $days));

        AuthEvent::query()
            ->where('created_at', '>=', $from)
            ->orderBy('id')
            ->chunkById(500, function ($events) use ($queue) {
                foreach ($events as $event) {
                    dispatch(new EnrichAuditEventJob((int) $event->id))->onQueue($queue);
                }
            });

        $this->info('OK: Re-enrich jobs dispatched.');
        return self::SUCCESS;
    }
}
