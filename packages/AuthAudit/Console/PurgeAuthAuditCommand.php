<?php
declare(strict_types=1);

namespace iEXPackages\AuthAudit\Console;

use iEXPackages\AuthAudit\Models\AuthEvent;
use Illuminate\Console\Command;

final class PurgeAuthAuditCommand extends Command
{
    /**
     * Команда очистки старых audit-событий.
     *
     * Пример:
     *   php artisan auth-audit:purge --days=90
     */
    protected $signature = 'auth-audit:purge
        {--days=90 : Удалить события старше указанного количества дней}
        {--dry-run : Показать, сколько записей будет удалено, без фактического удаления}';

    protected $description = 'Очищает старые записи из auth_audit_events';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');

        if ($days < 1) {
            $this->error('Параметр --days должен быть >= 1');
            return self::FAILURE;
        }

        $before = now()->subDays($days);

        $query = AuthEvent::query()
            ->where('created_at', '<', $before);

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('Нет записей для удаления.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("Dry-run: будет удалено {$count} записей (старше {$days} дней).");
            return self::SUCCESS;
        }

        // Удаляем батчами, чтобы не положить БД
        $deleted = 0;

        $query->orderBy('id')->chunkById(1000, function ($events) use (&$deleted) {
            $ids = $events->pluck('id')->all();
            $deleted += count($ids);

            AuthEvent::query()->whereIn('id', $ids)->delete();
        });

        $this->info("Удалено {$deleted} записей auth_audit_events (старше {$days} дней).");

        return self::SUCCESS;
    }
}
