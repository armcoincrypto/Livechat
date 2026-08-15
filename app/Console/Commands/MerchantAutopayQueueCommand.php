<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TaskStatusEnum;
use App\Models\Task;
use Carbon\Carbon;
use iEXPackages\Transaction\Facades\TransactionFacade;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class MerchantAutopayQueueCommand extends Command
{
    /**
     * Пример:
     * php artisan merchant:autopay-queue --chunk=200 --limit=2000
     * php artisan merchant:autopay-queue --dry-run
     */
    protected $signature = 'merchant:autopay-queue
        {--chunk=200 : Размер чанка выборки}
        {--limit=2000 : Максимум заявок за запуск}
        {--dry-run : Не менять БД, только показать статистику}';

    protected $description = 'Переводит оплаченные заявки (7) в очередь на выплату (16) при включенной авто-выплате.';

    public function handle(): int
    {
        // Глобальный флаг автоплаты
        if ((int) iEXSetting('is_enabled_autopay_cron') !== 1) {
            $this->info('Autopay disabled: is_enabled_autopay_cron=0');
            return self::SUCCESS;
        }

        $chunk = max(50, (int) $this->option('chunk'));
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $scanned = 0;
        $queued = 0;
        $skipped = 0;
        $queuedIds = [];

        // Берём только оплаченные заявки.
        // Доп. фильтры (is_auto_check_pay/is_bot) НЕ ставлю специально, т.к. оплата могла прийти callback'ом,
        // а нам важно, чтобы очередь работала одинаково для всех способов подтверждения оплаты.
        Task::query()
            ->select(['id', 'status'])
            ->where('status', TaskStatusEnum::PAID->value)
            ->orderBy('id')
            ->limit($limit)
            ->chunkById($chunk, function (Collection $tasks) use (&$scanned, &$queued, &$skipped, &$queuedIds, $dryRun) {
                foreach ($tasks as $task) {
                    $scanned++;

                    DB::beginTransaction();
                    try {
                        /** @var Task|null $locked */
                        $locked = Task::query()
                            ->with(['direction_exchange.currency2.gateway_payments', 'direction_exchange.gateway_payments'])
                            ->whereKey($task->id)
                            ->lockForUpdate()
                            ->first();

                        if (!$locked) {
                            DB::rollBack();
                            $skipped++;
                            continue;
                        }

                        // Если статус уже изменили — пропускаем
                        if ((int) $locked->status !== TaskStatusEnum::PAID->value) {
                            DB::rollBack();
                            $skipped++;
                            continue;
                        }

                        // State machine: проверяем разрешён ли переход 7 -> 16
                        $from = TaskStatusEnum::from((int) $locked->status);
                        if (!$from->canTransitionTo(TaskStatusEnum::PAYOUT_QUEUE)) {
                            DB::rollBack();
                            $skipped++;

                            Log::warning('autopay_queue_transition_denied', [
                                'task_id' => $locked->id,
                                'from' => $from->value,
                                'to' => TaskStatusEnum::PAYOUT_QUEUE->value,
                            ]);

                            continue;
                        }

                        // Проверяем наличие активных payout-гейтов (логика как в старом cron)
                        $transaction = TransactionFacade::init($locked);
                        $transaction->setIsCron(true);

                        $directionExchange = $transaction->getDirectionExchange();

                        $hasGatewayPayments = $directionExchange->gateway_payments
                            ->where('status', 1)
                            ->isNotEmpty();

                        $hasCurrencyPayments = $directionExchange->currency2?->gateway_payments
                            ?->where('status', 1)
                            ->isNotEmpty() ?? false;

                        if (!$hasGatewayPayments && !$hasCurrencyPayments) {
                            // Автовыплата невозможна — не ставим в очередь.
                            // Ничего не ломаем, просто логируем.
                            DB::commit();
                            $skipped++;

                            Log::info('autopay_queue_skipped_no_gateways', [
                                'task_id' => $locked->id,
                                'status' => (int) $locked->status,
                            ]);

                            continue;
                        }

                        if ($dryRun) {
                            DB::rollBack();
                            $queued++;
                            $queuedIds[] = (int) $locked->id;
                            continue;
                        }

                        // Переводим в очередь
                        $locked->status = TaskStatusEnum::PAYOUT_QUEUE->value;
                        $locked->updated_at = Carbon::now();
                        $locked->save();

                        DB::commit();
                        $queued++;
                        $queuedIds[] = (int) $locked->id;

                        Log::info('autopay_queue_enqueued', [
                            'task_id' => $locked->id,
                            'from' => TaskStatusEnum::PAID->value,
                            'to' => TaskStatusEnum::PAYOUT_QUEUE->value,
                            'source' => 'merchant:autopay-queue',
                        ]);
                    } catch (\Throwable $e) {
                        DB::rollBack();
                        $skipped++;

                        Log::error('autopay_queue_error', [
                            'task_id' => $task->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        $this->info(sprintf(
            'Done. scanned=%d queued=%d skipped=%d dryRun=%s%s',
            $scanned,
            $queued,
            $skipped,
            $dryRun ? '1' : '0',
            $queuedIds ? ' queued_ids=[' . implode(',', $queuedIds) . ']' : ''
        ));

        return self::SUCCESS;
    }
}
