<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Services;

use App\Models\MerchantTransactionData;
use App\Models\MerchantTransactionHash;
use App\Models\PayTransactionData;
use App\Models\Task;
use Carbon\Carbon;

/**
 * Строит “человеческую” шкалу прогресса по заявке без запросов к шлюзам.
 *
 * Идея:
 *  - только читаем состояние из БД (task/status/meta + tx data + ext_data)
 *  - выдаём набор шагов, которые UI рисует как timeline/progress bar
 *
 * Никаких правок шлюзов не требуется.
 */
final class PaymentProgressBuilder
{
    /**
     * Вернёт timeline для заявки.
     *
     * @return array{
     *   flow: 'incoming'|'outgoing'|'unknown',
     *   current_step: int,
     *   total_steps: int,
     *   steps: array<int, array{
     *     key: string,
     *     title: string,
     *     text: string,
     *     state: 'done'|'active'|'todo'|'warning'|'error',
     *     meta: array
     *   }>
     * }
     */
    public function forTask(Task $task): self
    {
        $this->task = $task;
        return $this;
    }

    private Task $task;

    public function build(): array
    {
        $flow = $this->detectFlow($this->task);

        if ($flow === 'incoming') {
            return $this->buildIncoming($this->task);
        }

        if ($flow === 'outgoing') {
            return $this->buildOutgoing($this->task);
        }

        return [
            'flow' => 'unknown',
            'current_step' => 1,
            'total_steps' => 1,
            'steps' => [[
                'key' => 'unknown',
                'title' => 'Не удалось определить процесс',
                'text' => 'Недостаточно данных для построения прогресса.',
                'state' => 'warning',
                'meta' => [],
            ]],
        ];
    }

    /**
     * Пытаемся определить поток:
     * - outgoing: есть PayTransactionData или статус 15/“ожидает выплату”
     * - incoming: есть MerchantTransactionData или transfer_to_account
     */
    private function detectFlow(Task $task): string
    {
        if ((int)($task->status ?? 0) === 15) {
            return 'outgoing';
        }

        $hasPay = PayTransactionData::where('id_task', $task->id)->exists();
        if ($hasPay) {
            return 'outgoing';
        }

        $hasMerchantTx = MerchantTransactionData::where('id_task', $task->id)->exists();
        if ($hasMerchantTx) {
            return 'incoming';
        }

        $transfer = trim((string)($task->transfer_to_account ?? ''));
        if ($transfer !== '') {
            return 'incoming';
        }

        return 'unknown';
    }

    // ---------------------------------------------------------------------
    // Incoming timeline
    // ---------------------------------------------------------------------

    private function buildIncoming(Task $task): array
    {
        $steps = [];

        $merchantTx = MerchantTransactionData::where('id_task', $task->id)->first();
        $hasAddress = $merchantTx && !empty($merchantTx->ext_data['wallet_number'] ?? '')
            || trim((string)($task->transfer_to_account ?? '')) !== '';

        $hashRow = MerchantTransactionHash::where('id_task', $task->id)->first();
        $txHash  = $hashRow?->transaction_hash ? (string)$hashRow->transaction_hash : '';

        $registerTx = (int)($task->register_tx ?? 0) === 1;

        // confirmations: если у тебя есть таблица/модель — подставь здесь.
        // Сейчас делаю максимально универсально: пытаюсь достать из ext_data/meta.
        $confirm = $this->readConfirmations($task);

        $final = $this->isIncomingFinal($task);

        // Шаг 1: Адрес/реквизиты
        $steps[] = $this->step(
            key: 'address',
            title: 'Реквизиты получены',
            text: $hasAddress ? 'Реквизиты для оплаты сформированы.' : 'Ожидаем создание реквизитов.',
            state: $hasAddress ? 'done' : 'active',
            meta: [
                'address' => (string)($merchantTx->ext_data['wallet_number'] ?? $task->transfer_to_account ?? ''),
                'tag'     => (string)($merchantTx->ext_data['memo_id'] ?? ''),
                'bank'    => (string)($merchantTx->ext_data['bank_name'] ?? ''),
            ]
        );

        // Шаг 2: Транзакция в сети
        $txFound = $registerTx || $txHash !== '';
        $steps[] = $this->step(
            key: 'tx_found',
            title: 'Транзакция найдена',
            text: $txFound ? 'Транзакция обнаружена в сети.' : 'Ожидаем появления транзакции в сети.',
            state: $txFound ? 'done' : ($hasAddress ? 'active' : 'todo'),
            meta: [
                'tx_hash' => $txHash,
                'register_tx' => $registerTx ? 1 : 0,
            ]
        );

        // Шаг 3: Подтверждения
        $needConfirm = ($confirm['required'] ?? 0) > 0;
        $enough = $needConfirm
            ? ((int)($confirm['current'] ?? 0) >= (int)($confirm['required'] ?? 0))
            : true;

        $state = 'todo';
        $text  = 'Подтверждения не требуются.';
        if ($needConfirm) {
            if (!$txFound) {
                $state = 'todo';
                $text = 'Подтверждения появятся после нахождения транзакции.';
            } elseif ($enough) {
                $state = 'done';
                $text = sprintf('Подтверждения получены: %d/%d.', (int)$confirm['current'], (int)$confirm['required']);
            } else {
                $state = 'active';
                $text = sprintf('Ожидаем подтверждения: %d/%d.', (int)$confirm['current'], (int)$confirm['required']);
            }
        }

        $steps[] = $this->step(
            key: 'confirmations',
            title: 'Подтверждения сети',
            text: $text,
            state: $state,
            meta: $confirm
        );

        // Шаг 4: Завершение
        $steps[] = $this->step(
            key: 'done',
            title: 'Оплата завершена',
            text: $final ? 'Оплата подтверждена и обработана.' : 'Ожидаем финальное подтверждение.',
            state: $final ? 'done' : ($txFound ? 'active' : 'todo'),
            meta: [
                'task_status' => (int)($task->status ?? 0),
            ]
        );

        $current = $this->currentStepIndex($steps);

        return [
            'flow' => 'incoming',
            'current_step' => $current,
            'total_steps' => count($steps),
            'steps' => $steps,
        ];
    }

    private function isIncomingFinal(Task $task): bool
    {
        // Подстрой под твою систему статусов.
        // Здесь пример: “успех” = 4, “отклонено” = 14
        $st = (int)($task->status ?? 0);

        return in_array($st, [4, 14], true);
    }

    private function readConfirmations(Task $task): array
    {
        // Если у тебя есть конкретные таблицы confirmations — лучше читать их тут.
        // Сейчас: пытаюсь достать из meta/ext_data максимально универсально.
        $required = 0;
        $current  = 0;

        try {
            $meta = $task->meta()->first();
            if ($meta && isset($meta->needed_confirm)) {
                $required = (int)$meta->needed_confirm;
            }
            if ($meta && isset($meta->received_confirm)) {
                $current = (int)$meta->received_confirm;
            }
        } catch (\Throwable) {
            // ignore
        }

        return [
            'current' => $current,
            'required' => $required,
        ];
    }

    // ---------------------------------------------------------------------
    // Outgoing timeline
    // ---------------------------------------------------------------------

    private function buildOutgoing(Task $task): array
    {
        $steps = [];

        $payTx = PayTransactionData::where('id_task', $task->id)->first();

        $hasCreated = $payTx && !empty($payTx->id_from_pay);
        $ext = is_array($payTx?->ext_data ?? null) ? (array)$payTx->ext_data : [];

        $trackingMode = (string)($ext['tracking_mode'] ?? '');
        $isCallback   = (int)($ext['is_callback'] ?? 0) === 1; // legacy compatibility
        $isWaitingHash = (int)($ext['is_waiting_hash'] ?? 0) === 1;

        $txHash = (string)($ext['transaction_hash'] ?? '');

        [$deadline, $attempts] = $this->readPayoutDeadlineAndAttempts($payTx);

        // Шаг 1: Выплата создана
        $steps[] = $this->step(
            key: 'payout_created',
            title: 'Выплата создана',
            text: $hasCreated ? 'Запрос на выплату успешно создан.' : 'Ожидаем создание выплаты.',
            state: $hasCreated ? 'done' : 'active',
            meta: [
                'external_id' => (string)($payTx?->id_from_pay ?? ''),
            ]
        );

        // Шаг 2: Ожидание статуса выплаты (cron/callback)
        $trackingActive = $hasCreated && ($isCallback || $trackingMode === 'cron' || $trackingMode === 'callback' || (int)($task->status_pay_api ?? 0) === 1);

        $steps[] = $this->step(
            key: 'payout_tracking',
            title: 'Проверка статуса выплаты',
            text: $trackingActive
                ? 'Ожидаем подтверждение выплаты от провайдера.'
                : 'Проверка статуса выплаты не активирована.',
            state: $trackingActive ? 'active' : ($hasCreated ? 'todo' : 'todo'),
            meta: [
                'tracking_mode' => $trackingMode ?: ($isCallback ? 'callback' : ''),
                'attempts' => $attempts,
                'deadline_at' => $deadline?->toDateTimeString(),
            ]
        );

        // Шаг 3: Ожидание tx_hash (если включено)
        $hashDone = $txHash !== '';
        $hashState = 'todo';
        $hashText = 'Ожидаем hash транзакции в сети.';
        if ($isWaitingHash) {
            $hashState = $hashDone ? 'done' : 'active';
            $hashText = $hashDone ? 'Hash транзакции получен.' : 'Ожидаем hash транзакции в сети.';
        } elseif ($hashDone) {
            $hashState = 'done';
            $hashText = 'Hash транзакции получен.';
        } else {
            // hash не требуется
            $hashState = 'todo';
            $hashText = 'Hash транзакции не требуется.';
        }

        $steps[] = $this->step(
            key: 'tx_hash',
            title: 'Hash транзакции',
            text: $hashText,
            state: $hashState,
            meta: [
                'tx_hash' => $txHash,
                'is_waiting_hash' => $isWaitingHash ? 1 : 0,
                'hash_attempts' => (int)($ext['hash_attempts'] ?? 0),
            ]
        );

        // Шаг 4: Завершение
        $final = $this->isOutgoingFinal($task);
        $steps[] = $this->step(
            key: 'done',
            title: 'Выплата завершена',
            text: $final ? 'Выплата успешно завершена.' : 'Ожидаем финальное завершение.',
            state: $final ? 'done' : ($hasCreated ? 'active' : 'todo'),
            meta: [
                'task_status' => (int)($task->status ?? 0),
            ]
        );

        $current = $this->currentStepIndex($steps);

        return [
            'flow' => 'outgoing',
            'current_step' => $current,
            'total_steps' => count($steps),
            'steps' => $steps,
        ];
    }

    private function readPayoutDeadlineAndAttempts(?PayTransactionData $tx): array
    {
        if (!$tx) {
            return [null, 0];
        }

        $ext = is_array($tx->ext_data ?? null) ? (array)$tx->ext_data : [];
        $deadlineRaw = $ext['payout_deadline_at'] ?? null;
        $attempts    = (int)($ext['payout_attempts'] ?? 0);

        $deadline = null;
        if ($deadlineRaw) {
            try {
                $deadline = Carbon::parse((string)$deadlineRaw);
            } catch (\Throwable) {
                $deadline = null;
            }
        }

        return [$deadline, $attempts];
    }

    private function isOutgoingFinal(Task $task): bool
    {
        // Подстрой под твою систему:
        // status 4 = success, 14 = failed, 15 = waiting payout
        $st = (int)($task->status ?? 0);

        return in_array($st, [4, 14], true);
    }

    // ---------------------------------------------------------------------
    // Utils
    // ---------------------------------------------------------------------

    private function step(string $key, string $title, string $text, string $state, array $meta): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'text' => $text,
            'state' => $state, // done|active|todo|warning|error
            'meta' => $meta,
        ];
    }

    private function currentStepIndex(array $steps): int
    {
        // текущий шаг = первый, который не done
        $i = 1;
        foreach ($steps as $step) {
            if (($step['state'] ?? '') !== 'done') {
                return $i;
            }
            $i++;
        }

        return max(1, count($steps));
    }
}
