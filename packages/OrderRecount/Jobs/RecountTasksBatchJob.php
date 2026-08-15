<?php

declare(strict_types=1);

namespace iEXPackages\OrderRecount\Jobs;

use App\Models\Task;
use iEXPackages\OrderRecount\Facades\OrderRecount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * RecountTasksBatchJob
 *
 * Пересчитывает пачку заявок одним Job'ом (batch), чтобы:
 * - не создавать отдельный Job на каждую заявку
 * - уменьшить нагрузку на очередь
 *
 * Важно:
 * - В режиме details печатает изменения курса (course_display и course_float).
 * - Для стабильности по памяти не грузит всю пачку через get(), а обрабатывает chunk'ами.
 */
final class RecountTasksBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param int[] $taskIds ID заявок для пересчёта.
     */
    public function __construct(
        public readonly array $taskIds,
        public readonly string $triggerType,
        public readonly bool $verbose = false,
    ) {
        $this->onQueue((string) config('order-recount.queue.recount', 'order-recount'));
    }

    public function handle(): void
    {
        $ids = $this->normalizeTaskIds($this->taskIds);
        if ($ids === []) {
            return;
        }

        $print = $this->verbose && app()->runningInConsole();

        if ($print) {
            $this->out(sprintf(
                '[OrderRecount] Batch started: tasks=%d, trigger=%s',
                count($ids),
                $this->triggerType
            ));
        }

        // Обрабатываем пачку chunk'ами — безопаснее по памяти, чем ->get()
        $this->queryByIds($ids)
            ->chunkById(200, function ($tasks) use ($print): void {
                foreach ($tasks as $task) {
                    if (!$print) {
                        OrderRecount::execute($task, $this->triggerType, (int) $task->status);
                        continue;
                    }

                    // BEFORE
                    $beforeDisplay = $this->normalizeText($task->course_display ?? null);
                    $beforeRate    = $this->normalizeText($task->course_float ?? null);

                    OrderRecount::execute($task, $this->triggerType, (int) $task->status);

                    // AFTER (важно: refresh, потому что пересчёт идёт через update())
                    $task->refresh();

                    $afterDisplay = $this->normalizeText($task->course_display ?? null);
                    $afterRate    = $this->normalizeText($task->course_float ?? null);

                    $changed = $this->isRateChanged(
                        beforeDisplay: $beforeDisplay,
                        afterDisplay: $afterDisplay,
                        beforeRate: $beforeRate,
                        afterRate: $afterRate
                    );

                    $this->out($this->formatLine(
                        taskId: (int) $task->id,
                        changed: $changed,
                        beforeDisplay: $beforeDisplay,
                        afterDisplay: $afterDisplay,
                        beforeRate: $beforeRate,
                        afterRate: $afterRate
                    ));
                }
            });

        if ($print) {
            $this->out('[OrderRecount] Batch finished');
        }
    }

    /**
     * Строит запрос по пачке taskIds.
     *
     * @param int[] $ids
     */
    private function queryByIds(array $ids): Builder
    {
        return Task::query()
            ->with(['direction_exchange.currency1'])
            ->whereIn('id', $ids)
            ->orderBy('id');
    }

    /**
     * Нормализует входной список ID.
     *
     * @param int[] $raw
     * @return int[]
     */
    private function normalizeTaskIds(array $raw): array
    {
        $set = [];

        foreach ($raw as $v) {
            $i = (int) $v;
            if ($i > 0) {
                $set[$i] = true;
            }
        }

        return array_keys($set);
    }

    private function normalizeText(mixed $v): string
    {
        $s = trim((string) ($v ?? ''));
        return $s;
    }

    private function isRateChanged(string $beforeDisplay, string $afterDisplay, string $beforeRate, string $afterRate): bool
    {
        // приоритет для человека: course_display
        if ($beforeDisplay !== '' && $afterDisplay !== '') {
            return $beforeDisplay !== $afterDisplay;
        }

        // fallback: course_float
        if ($beforeRate !== '' && $afterRate !== '') {
            return $beforeRate !== $afterRate;
        }

        // если до/после пустые, изменений “не видно”
        return false;
    }

    private function formatLine(
        int $taskId,
        bool $changed,
        string $beforeDisplay,
        string $afterDisplay,
        string $beforeRate,
        string $afterRate
    ): string {
        if (!$changed) {
            // Всё равно полезно печатать “что было”, если display пустой и люди путаются
            $snapshot = $beforeDisplay !== '' ? $beforeDisplay : ($beforeRate !== '' ? $beforeRate : '—');
            return sprintf('[OrderRecount] Task #%d: без изменений (курс: %s)', $taskId, $snapshot);
        }

        $beforeHuman = $beforeDisplay !== '' ? $beforeDisplay : $beforeRate;
        $afterHuman  = $afterDisplay !== '' ? $afterDisplay : $afterRate;

        // доп. строка для “машины”: показать float отдельно, если display есть
        $extra = '';
        if ($beforeDisplay !== '' || $afterDisplay !== '') {
            if ($beforeRate !== '' || $afterRate !== '') {
                $extra = sprintf(' | rate: %s → %s', $beforeRate !== '' ? $beforeRate : '—', $afterRate !== '' ? $afterRate : '—');
            }
        }

        return sprintf('[OrderRecount] Task #%d: курс изменился: %s → %s%s', $taskId, $beforeHuman, $afterHuman, $extra);
    }

    /**
     * Печать в STDERR — так надёжнее в queue-worker/Horizon.
     */
    private function out(string $line): void
    {
        if (defined('STDERR')) {
            fwrite(STDERR, $line . PHP_EOL);
            return;
        }

        // fallback
        echo $line . PHP_EOL;
    }
}
