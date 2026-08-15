<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Processors;

use App\Models\GatewayMerchant;
use App\Models\MerchantTransactionHash;
use App\Models\Task;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\DB;
use iEXPackages\Payments\Core\Contracts\FetchPaymentResponseInterface;
use iEXPackages\Payments\Core\Contracts\BlockchainPaymentResponseInterface;
use Throwable;

/**
 * Обработчик результата fetchPayment (polling) для входящих платежей.
 *
 * Делает:
 * - exists(): есть ли данные у провайдера
 * - cancelled: отмена/ошибка -> отдаёт результат через callback
 * - tx_hash: регистрация транзакции (register_tx) + сохранение hash
 * - confirmations: проверка подтверждений и возврат event not_min_confirm
 * - AML: вызов внешней проверки через callback
 *
 * ВАЖНО:
 * - Класс не знает про твою бизнес-архитектуру статусов/логов.
 *   Для этого принимает callback $logStatusChange.
 * - AML тоже через callback, чтобы не тащить зависимости модуля.
 */
final class IncomingPaymentPollingProcessor
{
    /**
     * @param Closure $logStatusChange function (array &$meta, int|string $status, string $text): void
     * @param Closure|null $handleCancelled function (object $response, array &$meta): array
     * @param Closure|null $amlCheck function (string $txHash, ?object $merchantTxData, array &$meta): void
     */
    public function __construct(
        private readonly Closure $logStatusChange,
        private readonly ?Closure $handleCancelled = null,
        private readonly ?Closure $amlCheck = null,
    ) {}

    /**
     * Основной метод обработки.
     *
     * @param Task $task Текущая заявка (Task).
     * @param GatewayMerchant $merchant Мерчант (входящий).
     * @param FetchPaymentResponseInterface $response Ответ fetchPayment.
     * @param array $meta Любая мета/лог-структура, которую ты ведешь.
     * @param int $minConfirm Минимальные подтверждения (если нужно).
     * @param object|null $merchantTxData Данные MerchantTransactionData (если нужно для AML).
     *
     * @return array{
     *   status:int,
     *   message?:string,
     *   event?:string
     * }
     */
    public function process(
        Task $task,
        GatewayMerchant $merchant,
        FetchPaymentResponseInterface $response,
        array &$meta,
        int $minConfirm = 0,
        ?object $merchantTxData = null,
    ): array {
        // 1) Нет данных у провайдера -> "не найдено"
        if (!$response->exists()) {
            return [
                'status'  => 1,
                'message' => 'Данные не получены',
            ];
        }

        // 2) Отмена
        if ($response->isCancelled()) {
            if ($this->handleCancelled !== null) {
                return ($this->handleCancelled)($response, $meta);
            }

            return [
                'status'  => 1,
                'message' => 'Платеж отменен',
            ];
        }

        // 3) Blockchain-ветка
        if ($response instanceof BlockchainPaymentResponseInterface) {
            $txHash = (string)($response->getTransactionHash() ?? '');

            // Если нет hash — дальше по блокчейну делать нечего
            if ($txHash === '') {
                return [
                    'status'  => 1,
                    'message' => 'Транзакция найдена, ожидаем появления hash',
                ];
            }

            // 3.1) register_tx (один раз)
            if ($response->canRegisterTransaction()) {
                DB::transaction(function () use ($task, &$meta): void {
                    $locked = $task->newQuery()->lockForUpdate()->find($task->id);

                    if (!$locked) {
                        return;
                    }

                    if ((int)$locked->register_tx === 0) {
                        $locked->update(['register_tx' => 1]);

                        ($this->logStatusChange)(
                            $meta,
                            'REGISTERING_TRANSACTION',
                            'Идет регистрация транзакции в сети'
                        );
                    }
                });

                $task->refresh();
            }

            // 3.2) сохраняем hash (только если register_tx включен)
            if ((int)$task->register_tx === 1) {
                DB::transaction(function () use ($task, $merchant, $txHash, &$meta): void {
                    $locked = $task->newQuery()
                        ->lockForUpdate()
                        ->with(['task_info', 'direction_exchange'])
                        ->find($task->id);

                    if (!$locked) {
                        return;
                    }

                    // Под lock: не будет гонок
                    $hashRow = MerchantTransactionHash::where('id_task', $locked->id)->first();

                    if (!$hashRow) {
                        MerchantTransactionHash::create([
                            'id_task'          => $locked->id,
                            'provider'         => (string)$merchant->alias,
                            'id_currency'      => (int)($locked->direction_exchange->id_currency1 ?? 0),
                            'transaction_hash' => $txHash,
                        ]);

                        ($this->logStatusChange)(
                            $meta,
                            'TRANSACTION_REGISTERED',
                            'Транзакция успешно зарегистрирована'
                        );
                    } else {
                        // Если hash изменился/пустой — обновим, но без повторного лога
                        if ((string)($hashRow->transaction_hash ?? '') !== $txHash) {
                            $hashRow->update([
                                'provider'         => (string)$merchant->alias,
                                'id_currency'      => (int)($locked->direction_exchange->id_currency1 ?? 0),
                                'transaction_hash' => $txHash,
                            ]);
                        }
                    }

                    // task_info.id_transaction_merchant (один раз)
                    if ($locked->task_info && empty($locked->task_info->id_transaction_merchant)) {
                        $locked->task_info->update(['id_transaction_merchant' => $txHash]);
                    }
                });

                $task->refresh();
            }

            // 3.3) Confirmations: два режима
            // A) новый метод needsMoreConfirmations + getConfirmations
            // B) legacy isPendingConfirmations + getTransactionConfirmations
            $needsMore = null;

            if (method_exists($response, 'needsMoreConfirmations')) {
                $needsMore = (bool)$response->needsMoreConfirmations();
            } elseif (method_exists($response, 'isPendingConfirmations')) {
                $needsMore = (bool)$response->isPendingConfirmations();
            }

            if ($needsMore === true) {
                ($this->logStatusChange)(
                    $meta,
                    'TRANSACTION_PENDING_CONFIRMATION',
                    'Транзакция найдена, ожидается подтверждение от сети'
                );

                $confirm = ['current' => 0, 'required' => 0];

                if (method_exists($response, 'getConfirmations')) {
                    $tmp = $response->getConfirmations();
                    if (is_array($tmp)) {
                        $confirm['current']  = (int)($tmp['current'] ?? 0);
                        $confirm['required'] = (int)($tmp['required'] ?? 0);
                    }
                } elseif (method_exists($response, 'getTransactionConfirmations')) {
                    $tmp = $response->getTransactionConfirmations();
                    if (is_array($tmp)) {
                        $confirm['current']  = (int)($tmp['current'] ?? 0);
                        $confirm['required'] = (int)($tmp['required'] ?? 0);
                    }
                } elseif (method_exists($response, 'getTransactionConfirmation') && $minConfirm > 0) {
                    // legacy: только текущие подтверждения
                    $confirm['current']  = (int)$response->getTransactionConfirmation();
                    $confirm['required'] = $minConfirm;
                }

                return [
                    'status'  => 1,
                    'event'   => 'not_min_confirm',
                    'message' => sprintf(
                        'Недостаточно подтверждений. Получено %d из %d.',
                        $confirm['current'],
                        $confirm['required']
                    ),
                ];
            }

            // 3.4) AML (если задан callback)
            if ($this->amlCheck !== null) {
                try {
                    ($this->amlCheck)($txHash, $merchantTxData, $meta);
                } catch (Throwable $e) {
                    return [
                        'status'  => 1,
                        'message' => sprintf('Ошибка AML-проверки: %s', $e->getMessage()),
                    ];
                }
            }
        }

        // Если дошли сюда — значит "данные есть", отмены нет, confirmations не блокируют, AML прошёл
        return [
            'status'  => 0,
            'message' => 'OK',
        ];
    }
}
