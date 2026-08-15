<?php

namespace App\Console\Commands;

use App\Models\Task;
use Carbon\Carbon;
use iEXPackages\Transaction\Facades\TransactionFacade;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WebhookMerchantCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'merchant:webhook';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Проверяем средства в фоновом режиме';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        Task::where([
            ['status', '=', 3],
            ['is_bot', '=', 1],
            ['is_auto_check_pay', '=', 1],
        ])->chunkById(100, function ($tasks) {

            // Сразу собираем ID для массового обновления
            $taskIds = $tasks->pluck('id')->toArray();

            // Массовое обновление времени следующей проверки
            Task::whereIn('id', $taskIds)->update(['next_checkout_at' => Carbon::now()->addSeconds(10)]);

            foreach ($tasks as $task) {
                $this->comment('Проверка заявки №' . $task->id);

                $transaction = TransactionFacade::init($task);
                $transaction->setIsCron(true);

                // Перевод заявки в ручной режим, если истекло время ожидания платежа
                if ($transaction->hasTimeOutCheckPayment()) {
                    $this->comment("Заявка #{$task->id} переведена в ручной режим (timeout)");
                    $hasConfirmed = \App\Models\WalletTransaction::query()
                        ->where('id_task', $task->id)
                        ->whereNull('deleted_at')
                        ->whereNotNull('txid')
                        ->where('txid', '!=', '')
                        ->exists();
                    $task->refresh();
                    if ($hasConfirmed && (int) $task->status !== 7) {
                        \App\Services\Orders\Transitions\PaymentTransitionMetrics::increment('confirmed_funds_wrong_status');
                        Log::critical('confirmed_funds_wrong_status', [
                            'event' => 'confirmed_funds_wrong_status',
                            'task_id' => $task->id,
                            'status' => (int) $task->status,
                        ]);
                    }
                    $task->update(['is_bot' => 0]);
                    continue;
                }

                try {
                    $transaction->setCheckAccurateBalance(true);
                    $checkPay = $transaction->checkInPayment();

                    // ВАЖНО: эта команда больше НЕ запускает выплаты.
                    // Она только подтверждает оплату (checkInPayment) и подготавливает данные.
                    // Выплата выполняется отдельными командами:
                    // - merchant:autopay-queue (7 -> 16)
                    // - merchant:autopay-run   (16 -> 15 -> 4/14)
                    if (($checkPay['status'] ?? 1) === 0) {
                        $directionExchange = $transaction->getDirectionExchange();

                        $hasGatewayPayments = $directionExchange->gateway_payments
                            ->where('status', 1)
                            ->isNotEmpty();

                        $hasCurrencyPayments = $directionExchange->currency2?->gateway_payments
                            ?->where('status', 1)
                            ->isNotEmpty() ?? false;

                        // Если выплатные шлюзы отсутствуют — переводим заявку в ручной режим,
                        // чтобы не зависала в авто-обработке.
                        if (!$hasGatewayPayments && !$hasCurrencyPayments) {
                            $transaction->disableIsBot();

                            $this->comment("Авто-выплата недоступна (нет активных payout-гейтов). Заявка #{$task->id} переведена в ручной режим.");
                            Log::info('autopay_disabled_no_gateways', [
                                'task_id' => $task->id,
                            ]);

                            continue;
                        }

                        // Оплата подтверждена. Дальше выплату обработают новые команды.
                        Log::info('payment_confirmed_waiting_autopay_pipeline', [
                            'task_id' => $task->id,
                        ]);
                    }
                } catch (\Throwable $exception) {
                    \App\Services\Orders\Transitions\PaymentTransitionMetrics::increment('payment_detector_transition_errors');
                    Log::error('payment_detector_exception', [
                        'event' => 'payment_detector_exception',
                        'task_id' => $task->id,
                        'error' => $exception->getMessage(),
                    ]);

                    $this->comment("Ошибка при проверке заявки #{$task->id}. Подробности в логах.");
                }
            }
        });
    }
}
