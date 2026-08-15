<?php

namespace iEXPackages\Transaction;

use App\Models\Task;
use iEXPackages\OrderRecount\Jobs\RecountTaskJob;
use Illuminate\Support\Facades\Cache;

class Transaction
{
    use Bindings\ManagersBootstrap,
        Bindings\ManagersClient,
        Bindings\ManagersDefer,
        Bindings\ManagersDetails,
        Bindings\ManagersReject,
        Bindings\ManagersNotification,
        Bindings\ManagersPayment,
        Bindings\ManagersSuccess,
        Concerns\ManagersReserves,
        Concerns\SupportsAutoPayments,
        Concerns\SupportsCheckPayment,
        Concerns\UpdateCustomFields,
        Concerns\AMLValidator;

    /**
     * Детали транзакции
     *
     * @var ?Task
     */
    protected ?Task $transaction = null;

    /**
     * ID Транзакции
     *
     * @return int
     */
    protected int $id;

    /**
     * Статус вычитывании комиссии из автоплатежа
     */
    protected bool $isConsoleCommission = false;

    /**
     * Запуск, использя крон
     */
    protected bool $isCron = false;

    /**
     * Дополнительные параметры транзакции
     */
    protected array $parameters = [
        'preview' => null,
        'commission' => 0,
        'type' => 'default',
        'category_reject' => 0,
        'otherFieldsForSuccess' => [],
        'pin_code' => null,
    ];

    /**
     * Создаем новый экземпляр транзакции.
     */
    public function __construct()
    {
        //
    }

    /**
     * Получение ID транзакции
     *
     * @throws \Exception
     */
    public function find($id, array $config = [], $trashed = false): Transaction
    {
        // Получение удаленной заявки
        if ($trashed) {
            $this->transaction = Task::withTrashed()->find($id);
            $this->parameters = $config;
            return $this;

        } elseif ($this->hasFind($id))
        {
            $this->transaction = Task::find($id);
            $this->parameters = $config;

            //            if(!$this->hasPreview()) {
            //                $this->bootstrap();
            //            }
            return $this;
        } else {
            throw new \Exception('Транзакции не существует');
        }
    }

    /**
     * Указать ID транзакции
     */
    public function setId(int $id): Transaction
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Вызвать событие для получения всех необходимых параметров
     */
    public function call($id): Transaction
    {
        $this->transaction = Task::find($id);

        return $this;
    }

    /**
     * Детали транзакции
     *
     * @throws \Exception
     */
    public function init(Task $task): Transaction
    {
        if (! isset($task)) {
            throw new \Exception('Транзакция не найдена');
        }

        $this->transaction = $task;

        return $this;
    }

    /**
     * Очистка данных
     */
    public function clear_init(): Transaction
    {
        $this->transaction = null;

        return $this;
    }

    /**
     * Получить тип транзакции.
     *
     * Возвращает тип транзакции, указанный в параметрах.
     * Если параметр 'type' не задан, возвращается значение 'default'.
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->parameters['type'] ?? 'default';
    }

    /**
     * Получаем сумму комиссии
     *
     * @return float
     */
    public function getCommission()
    {
        return $this->getTransferCommission();
    }

    /**
     * Причина отклонения заявки
     */
    public function getCategoryReject()
    {
        return $this->parameters['rejection_status'] ?? 1;
    }

    /**
     * Причины отклоения заявков
     *
     * @return Transaction
     */
    public function setCategoryReject($value)
    {
        if (! isset($this->parameters['rejection_status'])) {
            $this->parameters['rejection_status'] = $value;
        }

        return $this;
    }


    /**
     * Проверить актуальность транзакции
     */
    public function hasNotActive(): bool
    {
        return in_array($this->getStatus(), [3, 7]);
    }

    /**
     * Получить действией которая вызвана
     */
    public function hasAction()
    {
        return $this->parameters['actions'] ?? null;
    }

    /**
     * Удаленная заявка
     *
     * @return bool
     */
    public function isTrashed()
    {
        return request()->has('trashed') == true;
    }

    /**
     * Дает полное права обработать транзакцию по выбранным действиям
     *
     * @throws \Exception|\Throwable
     */
    public function handlerAction()
    {
        if ($this->hasAction() == 'run_process') {
            $this->start();
        } elseif ($this->hasAction() == 'payment') {
            $this->checkAndEnroll();

        } elseif ($this->hasAction() == 'logout') {
            $this->logout();
        } elseif ($this->hasAction() == 'notification') {
            $this->sendNotification($this->getMessage(), $this->getTypeMessage());

        } elseif ($this->hasAction() == 'success' and auth()->user()->can('admin_orders_execute')) {
            $this->success();

        } elseif ($this->hasAction() == 'defer') {
            $this->defer();

        } elseif ($this->hasAction() == 'reject' and auth()->user()->can('admin_orders_reject')) {
            $this->reject();

        } elseif ($this->hasAction() == 'recount' and auth()->user()->can('admin_orders_id_recount')) { // Устарел
            $this->recount();
        } elseif ($this->hasAction() == 'switch_to_manual_mode') {
            $this->disableIsBot();
        } elseif ($this->hasAction() == 'edit_data' and auth()->user()->can('admin_orders_id_editor')) {
            $this->updateEditData();
        } elseif ($this->hasAction() == 'change_operator' and auth()->user()->can('admin_orders_change_operator')) {
            if (! config('iexexchanger.is_reading_mode')) {
                $this->updateOperator();
            }
        } elseif ($this->hasAction() == 'delete_operator' and auth()->user()->can('admin_orders_change_operator')) {
            if (! config('iexexchanger.is_reading_mode')) {
                $this->deleteOperator();
            }
        }
    }


    /**
     * @deprecated
    */
    public function hasPreview()
    {
        return $this->parameters['preview'];
    }

    /**
     * Получение текст сообщения которая будет привязываться к опеределенным действиям
     *
     * @return string
     */
    public function getMessage()
    {
        return $this->parameters['message'];
    }

    /**
     * Тип отправки письма
     *
     * @return int
     */
    public function getTypeMessage()
    {
        return $this->parameters['type_message'];
    }

    /**
     * Не выплачить партнерские бонусы
     *
     * @return bool
     */
    public function getSwitchNotPartner()
    {
        return $this->parameters['notPartner'];
    }


    /**
     * Получаем доп. поля для информации после завершения заявки
     *
     * @return array
     */
    public function getOtherFieldsForSuccess(): array
    {
        return $this->parameters['otherFieldsForSuccess'] ?? [];
    }

    /**
     * Получение код безопасности
     *
     * @return string
     */
    public function getSecurityOrderCodeConfirm(): string
    {
        return (string)$this->parameters['pin_code'] ?? '';
    }

    /**
     * Получить детали транзакции
     */
    public function getItem(): ?Task
    {
        return $this->transaction;
    }

    /**
     * Проверяем, существует ли транзакция
     */
    protected function hasFind($id): bool
    {
        return Task::where('id', $id)->exists();
    }

    /**
     * Установка статус крон
     *
     * @return Transaction
     */
    public function setIsCron($value = false)
    {
        $this->isCron = $value;

        return $this;
    }

    /**
     * Получение статуса крон
     *
     * @return bool
     */
    public function getIsCron(): bool
    {
        return $this->isCron;
    }

    /**
     * Детали заявки
     */
    public function getTransaction(): Task
    {
        return $this->transaction;
    }


    /**
     * Запуск пересчёта при смене статуса (trigger: status-change).
     *
     * Важно:
     * - “Пересчитывать или нет” решают policies (order_recount_policies).
     * - type_recalculation_order — только включатель/выключатель триггера.
     * - Анти-спам: не ставим одинаковый job слишком часто.
     */
    public function recountOrderWithAllStatus(?int $status = null): void
    {
        $type = (int) iEXSetting('type_recalculation_order');

        // 0 = при смене статуса, 2 = оба варианта
        if ($type !== 0 && $type !== 2) {
            return;
        }

        $taskId = (int) $this->transaction->id;
        $st     = (int) ($status ?? $this->transaction->status);

        // Анти-спам: дедупликация коротким TTL
        $store = (string) config('order-recount.dedupe.store', 'redis');
        $ttl   = (int) config('order-recount.dedupe.status_change_ttl_seconds', 15);
        $ttl   = max(1, min($ttl, 300)); // 1..300 сек

        $pref  = (string) config('order-recount.dedupe.prefix', 'order-recount:dedupe');
        $pref  = trim($pref) !== '' ? trim($pref) : 'order-recount:dedupe';

        $key = "{$pref}:status-change:task:{$taskId}:status:{$st}";

        // Если указанный store недоступен — fallback на default store
        try {
            $allowed = Cache::store($store)->add($key, 1, $ttl);
        } catch (\Throwable) {
            $allowed = Cache::add($key, 1, $ttl);
        }

        if (!$allowed) {
            return;
        }

        RecountTaskJob::dispatch($taskId, 'status-change', $st)
            ->onQueue((string) config('order-recount.queue.recount', 'order-recount'))
            ->afterCommit();
    }
}
