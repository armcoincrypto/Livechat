<?php

declare(strict_types=1);

namespace iEXPackages\OrderRecount\Console;

use iEXPackages\OrderRecount\Jobs\ScanCronCandidatesJob;
use Illuminate\Console\Command;

final class OrderRecountRunCommand extends Command
{
    /**
     * Примеры:
     *  php artisan order-recount:run
     *  php artisan order-recount:run -d
     *  php artisan order-recount:run --chunk=1000 --max=50000 --batch=500
     *  php artisan order-recount:run --queue=order-recount-scan
     */
    protected $signature = 'order-recount:run
        {--d|details : Печатать в консоль по каждой заявке, что изменилось (для отладки)}
        {--chunk= : Размер чанка выборки (по умолчанию из конфига)}
        {--max= : Максимум заявок за запуск (по умолчанию из конфига)}
        {--batch= : Размер пачки заявок на один batch-job (по умолчанию из конфига)}
        {--queue= : Очередь для scan job (по умолчанию из конфига)}
    ';

    protected $description = 'OrderRecount: cron scan (batch) and dispatch recount jobs';

    public function handle(): int
    {
        $type = (int) iEXSetting('type_recalculation_order');

        // 1=cron, 2=оба
        if ($type !== 1 && $type !== 2) {
            $this->info(sprintf(
                'Cron-пересчёт отключён (type_recalculation_order=%d).',
                $type
            ));
            return self::SUCCESS;
        }

        $chunk = $this->optInt('chunk', (int) config('order-recount.scan.chunk', 500), 50, 5000);
        $max   = $this->optInt('max', (int) config('order-recount.scan.max_tasks_per_run', 20000), 1, 5_000_000);
        $batch = $this->optInt('batch', (int) config('order-recount.scan.batch_size', 200), 10, 5000);

        // batch не должен быть больше max
        if ($batch > $max) {
            $batch = $max;
        }

        $details = (bool) $this->option('details');

        $queue = (string) ($this->option('queue') ?: config('order-recount.queue.scan', 'order-recount-scan'));
        $queue = trim($queue) !== '' ? trim($queue) : 'order-recount-scan';

        ScanCronCandidatesJob::dispatch($chunk, $max, $batch, $details)
            ->onQueue($queue);

        $this->info(sprintf(
            'OrderRecount scan dispatched: queue="%s", chunk=%d, max=%d, batch=%d, details=%s (type_recalculation_order=%d).',
            $queue,
            $chunk,
            $max,
            $batch,
            $details ? 'yes' : 'no',
            $type
        ));

        return self::SUCCESS;
    }

    /**
     * Читает int-опцию, либо берёт default, и ограничивает диапазоном.
     */
    private function optInt(string $name, int $default, int $min, int $max): int
    {
        $raw = $this->option($name);

        if ($raw === null || $raw === '') {
            return $this->clampInt($default, $min, $max);
        }

        $val = is_numeric($raw) ? (int) $raw : $default;

        return $this->clampInt($val, $min, $max);
    }

    private function clampInt(int $v, int $min, int $max): int
    {
        if ($v < $min) return $min;
        if ($v > $max) return $max;
        return $v;
    }
}
