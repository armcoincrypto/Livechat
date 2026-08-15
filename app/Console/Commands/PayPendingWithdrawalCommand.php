<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PayTransactionData;
use App\Models\Task;
use iEXPackages\Payments\Core\Contracts\ResponseInterface;
use iEXPackages\Payments\Payments;
use iEXPackages\Transaction\Facades\TransactionFacade;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Проверка заявок в статусе 15 («Ожидает выплату»).
 *
 * Поддерживает два режима:
 *  - ext_data->is_callback = 1     : обычный трекинг статуса выплаты (fetchPayout)
 *  - ext_data->is_waiting_hash = 1: дополнительно ждём появления tx_hash (getTransactionHash из fetchPayoutResponse)
 *
 * Общая логика:
 *  - Инициализация таймера ожидания (deadline + attempts)
 *  - Если есть id_from_pay → fetchPayout
 *  - determineStatus → success/failed/pending
 *  - Если is_waiting_hash=1 → пробуем вытащить tx_hash, пишем в ext_data.transaction_hash, снимаем флаг
 *  - При дедлайне/лимите попыток — снимаем флаги и финализируем как failed
 */
final class PayPendingWithdrawalCommand extends Command
{
    protected $signature = 'pay:pending-withdrawal';
    protected $description = 'Проверка заявок в статусе 15 (Ожидает выплату): callback/cron + ожидание tx_hash.';

    private const DEFAULT_MAX_WAIT_MINUTES    = 60;
    private const DEFAULT_MAX_ATTEMPTS        = 60;
    private const DEFAULT_MIN_HASH_LENGTH     = 5;

    public function handle(): int
    {
        $this->info('🚀 Проверка заявок со статусом 15 (Ожидает выплату)');

        $maxWaitMinutes = (int) config('payments.callbacks.payout_wait_minutes', self::DEFAULT_MAX_WAIT_MINUTES);
        $maxAttempts    = (int) config('payments.callbacks.payout_max_attempts', self::DEFAULT_MAX_ATTEMPTS);
        $minHashLength  = (int) config('payments.callbacks.hash_min_length', self::DEFAULT_MIN_HASH_LENGTH);

        Task::query()
            ->where('status', 15)
            ->whereHas('pay_transaction_data', function ($q) {
                $q->where(function ($w) {
                    $w->where('ext_data->is_callback', 1)
                        ->orWhere('ext_data->is_waiting_hash', 1);
                });
            })
            ->with([
                'pay_transaction_data' => function ($q) {
                    $q->where(function ($w) {
                        $w->where('ext_data->is_callback', 1)
                            ->orWhere('ext_data->is_waiting_hash', 1);
                    });
                },
                'pay_transaction_data.pay',
            ])
            ->chunk(200, function ($tasks) use ($maxWaitMinutes, $maxAttempts, $minHashLength) {

                foreach ($tasks as $task) {
                    /** @var PayTransactionData|null $transaction */
                    $transaction = $task->pay_transaction_data;

                    // 1) Таймер ожидания
                    if ($transaction) {
                        $this->ensureWaitTimerInitialized((int) $task->id, $transaction, $maxWaitMinutes);
                    }

                    // 2) Если нет транзакции или внешнего id — нечего опрашивать
                    if (!$transaction || empty($transaction->id_from_pay)) {
                        $this->line("↪️ Заявка №{$task->id}: нет данных для проверки");

                        if ($this->hasExpired($transaction, $maxWaitMinutes)
                            || $this->attemptsExceeded($transaction, $maxAttempts)
                        ) {
                            $this->finalizeAsFailed($task, $transaction, 'истёк дедлайн ожидания или превышен лимит проверок');
                        }

                        continue;
                    }

                    // 3) Дедлайн и попытки (payout_attempts)
                    [$deadline, $attempts] = $this->readDeadlineAndAttempts($transaction, $maxWaitMinutes);
                    $isExpired = $deadline->isPast();

                    $this->comment("Заявка №{$task->id}: проверяем статус выплаты…");

                    $merchantPay = $transaction->pay;
                    if (!$merchantPay) {
                        $this->warn("Заявка №{$task->id}: не найден шлюз выплаты (GatewayPayment)");

                        if ($isExpired || $this->attemptsExceeded($transaction, $maxAttempts)) {
                            $this->finalizeAsFailed($task, $transaction, 'истёк дедлайн ожидания или превышен лимит проверок');
                        }

                        continue;
                    }

                    try {
                        $gatewayConnector = Payments::forPayment($merchantPay);

                        /** @var ResponseInterface $gatewayResponse */
                        $gatewayResponse = $gatewayConnector->fetchPayout([
                            'externalId' => (string) $transaction->id_from_pay,
                        ])->send();

                        // 3.1) Если нужно ждать tx_hash — пытаемся получить его из fetchPayout response
                        if ($this->shouldWaitTxHash($transaction)) {
                            $this->handleWaitingTxHash(
                                taskId: (int) $task->id,
                                transaction: $transaction,
                                response: $gatewayResponse,
                                maxAttempts: $maxAttempts,
                                minHashLength: $minHashLength
                            );
                        }

                        // 3.2) Статус выплаты
                        $statusPayApi = $this->determineStatus($gatewayResponse);

                        switch ($statusPayApi) {
                            case 3: // успех
                                $this->finalizeAsSuccess((int) $task->id, $transaction);
                                $this->info("✅ Заявка №{$task->id}: 15 → 4 (успешно)");
                                break;

                            case 2: // ошибка/отмена
                                $this->incrementAttempts($transaction, ++$attempts);
                                $this->finalizeAsFailed($task, $transaction, 'провайдер вернул ошибку/отмену');
                                break;

                            default: // pending
                                $this->incrementAttempts($transaction, ++$attempts);

                                if ($isExpired || $this->attemptsExceeded($transaction, $maxAttempts)) {
                                    $reason = $isExpired ? 'истёк лимит ожидания' : 'превышен лимит проверок';
                                    $this->finalizeAsFailed($task, $transaction, $reason);
                                } else {
                                    $left = now()->diffInMinutes($deadline, false);
                                    $this->line("⏳ Заявка №{$task->id}: в процессе… попыток={$attempts}, осталось ~{$left} мин");
                                }
                                break;
                        }

                    } catch (\Throwable $e) {
                        $this->error("❌ Заявка №{$task->id}: ошибка проверки выплаты: {$e->getMessage()}");

                        $this->incrementAttempts($transaction, ++$attempts);

                        if ($isExpired || $this->attemptsExceeded($transaction, $maxAttempts)) {
                            $this->finalizeAsFailed($task, $transaction, 'дедлайн истёк или превышен лимит проверок');
                        }
                    }
                }
            });

        $this->info('Проверка завершена');
        return self::SUCCESS;
    }

    // ---------------------------------------------------------------------
    // Таймер / дедлайн / попытки (payout_attempts)
    // ---------------------------------------------------------------------

    protected function ensureWaitTimerInitialized(int $taskId, PayTransactionData $transaction, int $maxWaitMinutes): void
    {
        $ext = is_array($transaction->ext_data ?? null) ? $transaction->ext_data : [];

        if (!empty($ext['payout_wait_started_at'])) {
            return;
        }

        $now      = Carbon::now();
        $deadline = (clone $now)->addMinutes($maxWaitMinutes);

        $transaction->jsonUpdate([
            'ext_data->payout_wait_started_at' => $now->toDateTimeString(),
            'ext_data->payout_deadline_at'     => $deadline->toDateTimeString(),
            'ext_data->payout_attempts'        => 0,

            // включаем мониторинг
            'ext_data->is_callback'            => 1,
        ]);

        $this->info("⏱ Заявка №{$taskId}: таймер проверки выплат запущен до {$deadline->format('Y-m-d H:i:s')}");
    }

    protected function readDeadlineAndAttempts(?PayTransactionData $transaction, int $maxWaitMinutes): array
    {
        if (!$transaction) {
            return [Carbon::now(), 0];
        }

        $ext = is_array($transaction->ext_data ?? null) ? $transaction->ext_data : [];

        $waitStartedRaw = $ext['payout_wait_started_at'] ?? null;
        $deadlineRaw    = $ext['payout_deadline_at']     ?? null;
        $attempts       = (int) ($ext['payout_attempts'] ?? 0);

        $waitStarted = $waitStartedRaw ? Carbon::parse($waitStartedRaw) : Carbon::now();
        $deadline    = $deadlineRaw ? Carbon::parse($deadlineRaw) : (clone $waitStarted)->addMinutes($maxWaitMinutes);

        return [$deadline, $attempts];
    }

    protected function hasExpired(?PayTransactionData $transaction, int $maxWaitMinutes): bool
    {
        [$deadline] = $this->readDeadlineAndAttempts($transaction, $maxWaitMinutes);
        return $deadline->isPast();
    }

    protected function attemptsExceeded(?PayTransactionData $transaction, int $maxAttempts): bool
    {
        if (!$transaction) {
            return false;
        }

        $ext = is_array($transaction->ext_data ?? null) ? $transaction->ext_data : [];
        $attempts = (int) ($ext['payout_attempts'] ?? 0);

        return $attempts >= $maxAttempts;
    }

    protected function incrementAttempts(PayTransactionData $transaction, int $attempts): void
    {
        $transaction->jsonUpdate(['ext_data->payout_attempts' => $attempts]);
    }

    // ---------------------------------------------------------------------
    // tx_hash ожидание (is_waiting_hash + hash_attempts)
    // ---------------------------------------------------------------------

    protected function shouldWaitTxHash(PayTransactionData $transaction): bool
    {
        $ext = is_array($transaction->ext_data ?? null) ? $transaction->ext_data : [];
        return (int) ($ext['is_waiting_hash'] ?? 0) === 1;
    }

    /**
     * Если is_waiting_hash=1:
     *  - пробуем взять hash из fetchPayoutResponse->getTransactionHash()
     *  - если получен: ext_data.transaction_hash, is_waiting_hash=0
     *  - если не получен: увеличиваем hash_attempts
     *  - если hash_attempts >= maxAttempts: is_waiting_hash=0
     */
    protected function handleWaitingTxHash(
        int $taskId,
        PayTransactionData $transaction,
        ResponseInterface $response,
        int $maxAttempts,
        int $minHashLength
    ): void {
        $ext = is_array($transaction->ext_data ?? null) ? $transaction->ext_data : [];
        $attempts = (int) ($ext['hash_attempts'] ?? 0);

        // Уже есть hash — снимаем ожидание
        $existingHash = (string) ($ext['transaction_hash'] ?? '');
        if (mb_strlen(trim($existingHash), 'UTF-8') >= $minHashLength) {
            $transaction->jsonUpdate(['ext_data->is_waiting_hash' => 0]);
            return;
        }

        // Пытаемся получить hash из ответа
        $hash = '';
        if (method_exists($response, 'getTransactionHash')) {
            $hash = trim((string) ($response->getTransactionHash() ?? ''));
        }

        if (mb_strlen($hash, 'UTF-8') >= $minHashLength) {
            $transaction->jsonUpdate([
                'ext_data->is_waiting_hash'  => 0,
                'ext_data->transaction_hash' => $hash,
                'ext_data->hash_attempts'    => $attempts,
            ]);
            $this->info("🔗 Заявка №{$taskId}: tx_hash получен: {$hash}");
            return;
        }

        // Не найден → +1
        $attempts++;

        if ($attempts >= $maxAttempts) {
            $transaction->jsonUpdate([
                'ext_data->is_waiting_hash' => 0,
                'ext_data->hash_attempts'   => $attempts,
            ]);
            $this->warn("🛑 Заявка №{$taskId}: tx_hash не получен за {$maxAttempts} попыток — ожидание отключено");
            return;
        }

        $transaction->jsonUpdate(['ext_data->hash_attempts' => $attempts]);
        $this->line("… Заявка №{$taskId}: tx_hash не найден, попытка #{$attempts}");
    }

    // ---------------------------------------------------------------------
    // Финализация
    // ---------------------------------------------------------------------

    protected function finalizeAsSuccess(int $taskId, PayTransactionData $transaction): void
    {
        TransactionFacade::find($taskId)->success(['skip_auto_payment' => true]);

        $transaction->jsonUpdate([
            'ext_data->is_callback'            => 0,
            'ext_data->is_waiting_hash'        => 0,
            'ext_data->hash_attempts'          => 0,

            'ext_data->payout_attempts'        => 0,
            'ext_data->payout_wait_started_at' => null,
            'ext_data->payout_deadline_at'     => null,
        ]);
    }

    protected function finalizeAsFailed(Task $task, ?PayTransactionData $transaction, string $reason): void
    {
        $transaction->jsonUpdate([
            'ext_data->is_callback'     => 0,
            'ext_data->is_waiting_hash' => 0,
        ]);

        $this->error("Заявка №{$task->id}: отклонена — {$reason}");
    }

    // ---------------------------------------------------------------------
    // Статусы провайдера
    // ---------------------------------------------------------------------

    /**
     * 1 — pending, 2 — failed/canceled, 3 — success
     */
    protected function determineStatus(ResponseInterface $response): int
    {
        if ($response->isCancelled()) {
            return 2;
        }
        if ($response->isSuccessful()) {
            return 3;
        }
        return 1;
    }
}
