<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TaskStatusEnum;
use App\Models\Task;
use App\Models\TaskInfo;
use iEXPackages\Transaction\Facades\TransactionFacade;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class MerchantAutopayRunCommand extends Command
{
    protected $signature = 'merchant:autopay-run
        {--chunk=10 : Размер чанка выборки}
        {--limit=10 : Максимум заявок за запуск}
        {--dry-run : Не менять БД, только показать статистику}';

    protected $description = 'Запускает авто-выплату для заявок в очереди (16) ровно один раз. Метка: task_info.autopayout_token + autopayout_started_at. Статусы не перетираются.';

    public function handle(): int
    {
        if ((int) iEXSetting('is_enabled_autopay_cron') !== 1) {
            $this->info('Autopay disabled: is_enabled_autopay_cron=0');
            return self::SUCCESS;
        }

        $chunk = max(1, (int) $this->option('chunk'));
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $scanned = 0;
        $leased = 0;
        $executed = 0;
        $skipped = 0;

        Task::query()
            ->select(['id', 'status'])
            ->where('status', TaskStatusEnum::PAYOUT_QUEUE->value) // 16
            ->where(function ($q) {
                $q->whereDoesntHave('task_info')
                    ->orWhereHas('task_info', function ($qq) {
                        $qq->whereNull('autopayout_token');
                    });
            })
            ->orderBy('id')
            ->limit($limit)
            ->chunkById($chunk, function (Collection $tasks) use (
                &$scanned,
                &$leased,
                &$executed,
                &$skipped,
                $dryRun
            ): void {
                foreach ($tasks as $task) {
                    $scanned++;

                    $token = $this->leaseOnce((int) $task->id, $dryRun);
                    if ($token === null) {
                        $skipped++;
                        continue;
                    }

                    $leased++;

                    if ($dryRun) {
                        continue;
                    }

                    try {
                        $freshTask = Task::query()->findOrFail((int) $task->id);

                        // Если статус уже изменили (не 16) — не продолжаем
                        if ((int) $freshTask->status !== TaskStatusEnum::PAYOUT_QUEUE->value) {
                            $skipped++;
                            Log::warning('autopay_run_status_changed_before_payment', [
                                'task_id' => (int) $freshTask->id,
                                'status' => (int) $freshTask->status,
                                'token' => $token,
                            ]);
                            continue;
                        }

                        $transaction = TransactionFacade::init($freshTask);
                        $transaction->setIsCron(true);

                        // Проверка payout-гейтов — ок оставлять как есть
                        if (!$this->hasActivePayoutGateways($transaction)) {
                            $transaction->disableIsBot();

                            Log::info('autopay_run_no_gateways', [
                                'task_id' => (int) $freshTask->id,
                                'token' => $token,
                            ]);

                            continue;
                        }

                        /** @var mixed $response */
                        $response = $transaction->autoPaymentViaCron();
                        $executed++;

                        // Мы НЕ перетираем статус. Просто фиксируем, что стало после сервиса.
                        $after = Task::query()->findOrFail((int) $freshTask->id);

                        Log::info('autopay_run_observed_status', [
                            'task_id' => (int) $after->id,
                            'token' => $token,
                            'status' => (int) $after->status,
                            'response_status' => is_array($response) && array_key_exists('status', $response)
                                ? (int) $response['status']
                                : null,
                        ]);
                    } catch (\Throwable $e) {
                        Log::error('autopay_run_exception', [
                            'task_id' => (int) $task->id,
                            'token' => $token,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        $this->info(sprintf(
            'Done. scanned=%d leased=%d executed=%d skipped=%d dryRun=%s',
            $scanned,
            $leased,
            $executed,
            $skipped,
            $dryRun ? '1' : '0'
        ));

        return self::SUCCESS;
    }

    /**
     * “Лизинг” запуска выплаты ровно 1 раз:
     * - задача должна быть в статусе 16
     * - token должен быть NULL
     * - started_at ставим только один раз (если NULL)
     *
     * Возвращает token или null (если уже запускали/не подходит).
     */
    private function leaseOnce(int $taskId, bool $dryRun): ?string
    {
        return DB::transaction(function () use ($taskId, $dryRun): ?string {
            /** @var Task|null $task */
            $task = Task::query()
                ->whereKey($taskId)
                ->lockForUpdate()
                ->first();

            if (!$task) {
                return null;
            }

            if ((int) $task->status !== TaskStatusEnum::PAYOUT_QUEUE->value) {
                return null;
            }

            $token = (string) Str::uuid();

            if ($dryRun) {
                return $token;
            }

            // Гарантируем строку TaskInfo
            TaskInfo::query()->updateOrCreate(
                ['id_task' => (int) $task->id],
                [] // ничего не пишем здесь, дальше всё условно
            );

            // 1) Ставим token только если он ещё NULL (если update=0 → уже запускали)
            $updated = TaskInfo::query()
                ->where('id_task', (int) $task->id)
                ->whereNull('autopayout_token')
                ->update([
                    'autopayout_token' => $token,
                ]);

            if ($updated !== 1) {
                Log::warning('autopay_run_already_leased', [
                    'task_id' => (int) $task->id,
                ]);

                return null;
            }

            // 2) Ставим дату запуска, только если она NULL (без DB::raw)
            TaskInfo::query()
                ->where('id_task', (int) $task->id)
                ->where('autopayout_token', $token)
                ->whereNull('autopayout_started_at')
                ->update([
                    'autopayout_started_at' => now(),
                ]);

            Log::info('autopay_run_leased', [
                'task_id' => (int) $task->id,
                'token' => $token,
            ]);

            return $token;
        });
    }

    private function hasActivePayoutGateways(object $transaction): bool
    {
        $directionExchange = $transaction->getDirectionExchange();

        $hasGatewayPayments = $directionExchange->gateway_payments
            ->where('status', 1)
            ->isNotEmpty();

        $hasCurrencyPayments = $directionExchange->currency2?->gateway_payments
            ?->where('status', 1)
            ->isNotEmpty() ?? false;

        return $hasGatewayPayments || $hasCurrencyPayments;
    }
}
