<?php

namespace iEXPackages\Transaction\Bindings;

use App\Events\OrderChatMessagesEvent;
use App\Events\OrderChatNotificationEvent;
use App\Jobs\OrderChatJob;
use App\Models\ApplicationStepLog;
use App\Models\HistoryRecalculation;
use App\Models\MerchantTransactionData;
use App\Models\OperationLevel;
use App\Models\Task;
use App\Models\TaskMessage;
use App\Models\TaskOperator;
use App\Models\TaskShot;
use App\Models\TasksOperatorLog;
use App\Models\VerificationCard;
use Carbon\Carbon;
use iEXPackages\Calculator\CalculatorFacade;
use iEXPackages\OrderChat\Contracts\OrderChatServiceInterface;
use iEXPackages\Transaction\FeeCalculator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\IpUtils;
use Throwable;

trait ManagersDetails
{
    /**
     * Изменить этап заявки
     */
    public function changeOrderStep(int $type)
    {
        // Записываем в лог этапы для заявок
        ApplicationStepLog::create([
            'id_step' => $type,
            'id_manager' => \auth()->id(),
            'id_task' => $this->transaction->id,
            'id_order_status' => $this->transaction->status,
        ]);

        $this->transaction->update([
            'id_order_step' => $type,
        ]);
    }

    /**
     * Дополнительная защита для мерчанта (Ограниченные IP-адреса)
     */
    public function isMerchantValidIpRange(string $ip): bool
    {
        $allowedIps = collect(preg_split('/[\s,]+/', $this->getMerchant()->allow_ip_address))
            ->map(fn($item) => trim($item))
            ->filter()
            ->toArray();

        return IpUtils::checkIp($ip, $allowedIps);
    }

    /**
     * Сумма, которую отдал клиент (для отображения).
     */
    public function getDisplayInPrice(bool $split = false): mixed
    {
        $givePrice = $this->transaction->give_price_with_comm;

        if (!$split && (int)iEXSetting('is_order_view_formatted_amount') === 1) {
            $decimalPlaces = strlen(substr(strrchr((string)$givePrice, '.'), 1));

            return iex_number_format($givePrice, $decimalPlaces, false);
        }

        return $givePrice;
    }

    /**
     * Сумма, которую переводит сервис (для отображения).
     */
    public function getDisplayOutPrice(bool $split = false): mixed
    {
        $receivingPrice = $this->transaction->receiving_price_with_comm_pay;

        if (!$split && (int)iEXSetting('is_order_view_formatted_amount') === 1) {
            // Получаем количество знаков после запятой
            $decimalPlaces = strlen(substr(strrchr((string)$receivingPrice, '.'), 1));

            return iex_number_format($receivingPrice, $decimalPlaces);
        }

        return $receivingPrice;
    }

    /**
     * Сумма, которую переводит сервис (для отображения) — с вычетом комиссии.
     */
    public function getDisplayOutPriceFee(bool $split = false): mixed
    {
        $outPriceFee = $this->transaction->out_price_fee;

        if (!$split && (int)iEXSetting('is_order_view_formatted_amount') === 1) {
            $decimalPlaces = strlen(substr(strrchr((string)$outPriceFee, '.'), 1));

            return iex_number_format($outPriceFee, $decimalPlaces, false);
        }

        return $outPriceFee;
    }
    /**
     * Получение статуса транзации
     *
     * @return int
     */
    public function getStatus()
    {
        return $this->transaction->status;
    }

    public function getStatusName()
    {
        return $this->transaction->task_status->name;
    }

    public function getCurrentCourse()
    {
        return $this->transaction->course_display;
    }

    /**
     * Проверяет, верифицирован ли счёт пользователя (карта).
     */
    public function isVerifiedCard(): bool
    {
        $cardNumber = preg_replace('/\s+/', '', $this->transaction->from_shot);

        return VerificationCard::query()
            ->where('id_user', $this->transaction->id_user)
            ->whereIdentifier($cardNumber)
            ->where('id_currency', $this->transaction->direction_exchange->id_currency1)
            ->where('status', 1)
            ->exists();
    }

    /**
     * Проверка необходимости верификации карты.
     *
     * @param array|null $currency
     * @return int
     */
    public function isVerificationCard(?array $currency = null): int
    {
        if ($currency === null) {
            $currency = $this->getCurrencyIn()->toArray();
        }

        // Если верификация отключена
        if ((int)$currency['is_enabled_verification'] === 0) {
            return 1;
        }

        // Если верификация по минимальной сумме и сумма не превышена
        if (
            (int)$currency['is_enabled_verification'] === 2 &&
            $this->getAmountIn() < (float)$currency['min_amount_verification']
        ) {
            return 1;
        }

        // Иначе проверяем статус верификации карты
        return $this->statusVerificationCard();
    }

    private function statusVerificationCard()
    {
        // Номер счета (Отдаю)
        $account_number = preg_replace('/\s/', '', $this->transaction->from_shot);

        $hasVerify = VerificationCard::query()
            ->where('email', $this->transaction->email)
            ->whereIdentifier($account_number)
            ->where('id_currency', $this->transaction->direction_exchange->id_currency1)
            ->where('status', 1)
            ->exists();

        if (! $hasVerify) {
            $hasVerifyCheck = VerificationCard::query()
                ->where('id_order', $this->transaction->id)
                ->where('email', $this->transaction->email)
                ->whereIdentifier($account_number)
                ->where('id_currency', $this->transaction->direction_exchange->id_currency1)
                ->whereIn('status', [0, 1])
                ->first();

            return $hasVerifyCheck->status ?? -1;
        }

        return $hasVerify == 1 ?? -1;
    }

    public function isEmailVerificationModal()
    {
        if ($this->getCurrencyIn()->is_email_verification_modal == 0) {
            return true;
        }

        return auth()->check() and ! is_null(auth()->user()->email_verified_at);
    }

    /**
     * Получение счетчика заявок  за день
     */
    public function getCountDayOrderIn(): int
    {
        return Task::whereHas('direction_exchange', function ($query) {
            $query->where('id_currency1', '=', $this->transaction->direction_exchange->id_currency1);
        })->whereIn('status', [3, 4])
            ->whereBetween('created_at', [
                Carbon::today()->startOfDay(),
                Carbon::today()->endOfDay(),
            ])
            ->count();
    }

    /**
     * Комиссия от перевода
     *
     * @return float|int
     */
    public function getTransferCommission()
    {
        if ($this->getCurrencyOut()->transfer_amount_reserve > 0) {
            return $this->getCurrencyOut()->transfer_amount_reserve;
        } elseif ($this->getCurrencyOut()->transfer_percent_reserve > 0) {
            $amount = ($this->getAmountOut() * $this->getCurrencyOut()->transfer_percent_reserve / 100);

            return iex_number_format($amount, $this->getCurrencyOut()->number_format);
        }

        return 0;
    }

    /**
     * Переводим в ручной режим
     */
    public function disableIsBot()
    {
        $this->transaction->update([
            'is_bot' => 0,
            'is_autopay_off' => 1,
        ]);
    }

    /**
     * Отключаем все автовыплаты если зафиксировано двойное снятие средств
     */
    public function turnOffAutoOutput(): void
    {
        $this->transaction->update([
            'double_withdrawal' => 1,
            'is_bot' => 0,
        ]);
    }

    /**
     * Проверяем номер счета получателя на уникальность
     */
    public function uniqueToShot()
    {
        $shot = TaskShot::where('id_task', '=', $this->transaction->id)->first();

        if (isset($shot)) {
            return $shot->account == $this->transaction->to_shot;
        }

        return true;
    }

    /**
     * Получение номера кошелька
     *
     * @return mixed
     */
    public function getAddresses(): mixed
    {
        $direction   = $this->transaction->direction_exchange;
        $currencyIn  = $this->getCurrencyIn();

        // Флаг "реквизиты по запросу" с приоритетом направления над валютой
        $isRequestMode = false;

        if ($direction && (int)($direction->method_request_payment ?? 0) === 1) {
            $isRequestMode = true;
        } elseif ($currencyIn && (int)($currencyIn->method_request_payment ?? 0) === 1) {
            $isRequestMode = true;
        }

        // Если включена выдача реквизитов по запросу — показываем то, что клиент указал в заявке
        if ($isRequestMode) {
            return $this->transaction->requisites_receive !== null && $this->transaction->requisites_receive !== ''
                ? $this->transaction->requisites_receive
                : __('Не определен');
        }

        if (!empty($this->transaction->transfer_to_account)) {
            return $this->transaction->transfer_to_account;
        }

        if (!is_null($this->transaction->direction_requisites)) {
            return $this->transaction->direction_requisites->account_number;
        }

        $merchantData = MerchantTransactionData::where('id_task', $this->transaction->id)->first();
        if (isset($merchantData->ext_data)) {
            return $merchantData->ext_data;
        }

        return __('Не определен');
    }

    /**
     * Информация по картам
     */
    public function getCardDetails($type = 'in')
    {
        if (isset($this->transaction->tasks_card_detail_in) and $type == 'in') {
            return $this->transaction->tasks_card_detail_in;
        }

        if (isset($this->transaction->tasks_card_detail_out) and $type == 'out') {
            return $this->transaction->tasks_card_detail_out;
        }

        return [];
    }

    /**
     * Отключаем бота в случае неполного поступления средств от клиента
     *
     * @return void
     */
    public function inFlowFunds()
    {
        $this->transaction->update([
            'in_flow_funds' => 1,
            'is_bot' => 0,
        ]);
    }

    /**
     * Доп. поля для направлений
     */
    public function getTasksFields(): array
    {
        $requisites_info_fields = $this->transaction->payment_requisites->requisites_info_fields ?? [];
        $many_fields = $this->transaction->tasks_fields_base ?? [];
        $user_order_fields = $this->transaction->tasks_fields_user_order ?? [];

        return [
            'many' => !empty($many_fields) ? $many_fields->map(function($item) {
                return [
                    'field_name' => $item->field_name,
                    'field_value' => $item->field_value
                ];
            })->reject(function($item) {
                return empty($item['field_value']);
            }) : [],
            'currency_in' => $this->transaction->tasks_fields_currency_in ?? [],
            'currency_out' => $this->transaction->tasks_fields_currency_out ?? [],

            'user_order' => !empty($user_order_fields)
                ? $user_order_fields->map(function ($item) {
                    return [
                        'field_name'  => $item->field_name,
                        'field_value' => $item->field_value,
                        'field_key'   => $item->field_key,
                    ];
                })->reject(function ($item) {
                    return empty($item['field_value']);
                })
                : [],

            'requisites_info_field' => !empty($requisites_info_fields) ? $requisites_info_fields->map(function ($item) {
                return [
                    'key_name' => $item->key_name,
                    'value_name' => $item->value_name
                ];
                }) : [],
        ];
    }

    /**
     * Доп. поля для отдаю
     */
    public function inOrderAdditionFields()
    {
        if (isset($this->transaction->tasks_in_addition_fields)) {
            return $this->transaction->tasks_in_addition_fields;
        }

        return [];
    }

    /**
     * Доп. поля для получаю
     */
    public function outOrderAdditionFields()
    {
        if (isset($this->transaction->tasks_out_addition_fields)) {
            return $this->transaction->tasks_out_addition_fields;
        }

        return [];
    }

    /**
     * Получаем название custom название кошелька
     *
     * @return string | mixed
     */
    public function getFieldAccountNumber()
    {
        $currency = $this->getCurrencyIn();

        return $currency?->account_number_field ?? '';
    }

    /**
     * Установка нового статуса.
     *
     * ВАЖНО:
     * - Сначала обновляем статус в БД
     * - Затем делаем операции с резервами (по актуальному состоянию)
     * - И только потом запускаем пересчёт через OrderRecount (job)
     *
     * @throws InvalidArgumentException
     */
    public function setStatus($status): static
    {
        $status = (int) $status;

        if ($status === 4 && empty($this->parameters['allow_complete_status_write'])) {
            throw \App\Services\Orders\ManualCompletion\ManualCompletionException::bypassForbidden();
        }

        $update = [
            'status' => $status,
        ];

        if ($status === 3 || $status === 7) {
            $update['started_at'] = \Illuminate\Support\Carbon::now()->toDateTimeString();
        }

        if (in_array($status, [5, 10], true)) {
            $update['id_rejection_status'] = $this->getCategoryReject();
        }

        if (in_array($status, [4, 5, 8], true)) {
            $update['is_bot'] = 0;
        }

        // 1) Пишем в БД
        $this->transaction->update($update);

        // 2) Обязательно обновляем инстанс модели, чтобы флаги резервов были актуальными
        $this->transaction->refresh();

        // 3) Резервы
        if (in_array($status, [5, 8, 10, 11], true)) {
            if ((int)$this->transaction->is_reserve_out_used === 1) {
                $this->resetReserveAll();
                $this->transaction->update([
                    'is_reserve_in_used' => 0,
                    'is_reserve_out_used' => 0,
                ]);
                $this->transaction->refresh();
            }
        }

        if (in_array($status, [3, 7], true)) {
            if ((int)$this->transaction->is_reserve_out_used === 0) {
                $this->updateReserveOut();
                $this->transaction->update(['is_reserve_out_used' => 1]);
                $this->transaction->refresh();
            }
        }

        if ($status === 4) {
            if ((int)$this->transaction->is_reserve_in_used === 0) {
                $this->transaction->update(['is_reserve_in_used' => 1]);
                $this->transaction->refresh();
                $this->updateReserveIn();
            }
        }

        // 4) В конце — пересчёт по новому статусу (OrderRecount решит, нужен ли он)
        $this->recountOrderWithAllStatus($status);

        return $this;
    }

    public function getLeadTime()
    {
        $start = Carbon::parse($this->transaction->started_at);
        $lead_time = Carbon::parse($this->transaction->updated_at);

        $getSecond = $start->diffForHumans($lead_time, true);

        return $getSecond;
    }

    /**
     * Минимальная цена обмена
     *
     * @return float
     */
    public function getOrderMinAmount()
    {
        return (float) $this->transaction->task_info->in_min_amount;
    }

    /**
     * Максимальная цена обмена
     *
     * @return float
     */
    public function getOrderMaxAmount()
    {
        return (float) $this->transaction->task_info->in_max_amount;
    }

    /**
     * Получаем EXMO Code который отправил клиент
     *
     * @return string
     */
    public function getExCode()
    {
        return $this->transaction->excode;
    }

    /**
     * Получаем Kuna Code который отправил клиент
     *
     * @return string
     */
    public function getKunaCode()
    {
        return $this->transaction->excode;
    }

    /**
     * Получаем WhiteBit который отправил клиент
     *
     * @return string
     */
    public function getWhiteBit()
    {
        return $this->transaction->excode;
    }

    /**
     * Запускаем процесс выполнения транзакции
     *
     * @return $this
     */
    public function start()
    {
        $this->transaction->update(['start' => 1]);

        return $this;
    }

    /**
     * Сбрасываем процесс выполнения транзакции
     *
     * @return $this
     */
    public function unstart()
    {
        $this->transaction->update(['start' => 0]);

        return $this;
    }

    /**
     * Проверяем запущена ли заявка
     *
     * @return bool
     */
    public function isStart()
    {
        return $this->transaction->start;
    }

    /**
     * Пересчитать сумму заявки (пересчёт курса/комиссий) без зависимости от HTTP Request.
     *
     * История пересчёта:
     * - Пишем историю ПОСЛЕ успешного пересчёта (чтобы не было ложных записей при ошибке).
     * - В историю кладём:
     *     old_amount = старое значение "получаю" (amount out) до пересчёта
     *     amount     = новое значение "получаю" (amount out) после пересчёта
     *     type       = at_rate
     *     course     = отображаемый курс (строка)
     *     course_value = числовой курс
     *
     * @param int $at_rate
     *   0 — авто (используем текущий курс заявки)
     *   2 — фиксированный курс (final_adjustment = -100)
     *   3 — ручной курс (default_rate = $manualRate)
     *   4 — ручная корректировка (final_adjustment = $manualRate как выражение)
     * @param float|int $give Сумма "отдал клиент". Если 0 — берём из заявки (getAmountIn()).
     * @param string $manualRate Для режимов 3/4.
     *
     * @throws \Throwable
     */
    public function recount(int $at_rate = 0, float|int $give = 0, string $manualRate = '0'): void
    {
        $at_rate = (int) $at_rate;

        if (!in_array($at_rate, [0, 2, 3, 4, 1], true)) {
            // 1 оставляем как "обычный пересчёт" (исторически у тебя он используется)
            // если не нужен — можно убрать
            $at_rate = 1;
        }

        if ((int)($this->transaction->floating_recount_stop ?? 0) === 1) {
            Log::info("Пересчёт заявки #{$this->transaction->id} остановлен (floating_recount_stop).");
            return;
        }

        // Нормализуем сумму "отдал клиент"
        $in = normalize_amount_to_float(
            $give == 0 ? (string)$this->getAmountIn() : (string)$give
        );

        if ($in <= 0) {
            throw new \InvalidArgumentException('Некорректная сумма для пересчёта.');
        }

        // Сохраняем значения ДО пересчёта (для истории и резервов)
        $oldOut = normalize_amount_to_float((string)$this->getAmountOut()); // "получаю" до пересчёта

        $manualRateValue = 0.0;
        $finalAdjustmentExpression = null;

        if ($at_rate === 3) {
            $manualRateValue = normalize_amount_to_float($manualRate);
            if ($manualRateValue <= 0) {
                throw new \InvalidArgumentException('Некорректный ручной курс.');
            }
        }

        if ($at_rate === 4) {
            $finalAdjustmentExpression = trim((string)$manualRate);
            if ($finalAdjustmentExpression === '') {
                throw new \InvalidArgumentException('Не указано выражение для ручной корректировки курса.');
            }
        }

        // Всё, что меняет заявку и пишет историю — выполняем атомарно
        \DB::transaction(function () use (
            $at_rate,
            $in,
            $manualRateValue,
            $finalAdjustmentExpression,
            $oldOut
        ): void {
            // 1) Опции калькулятора
            $calculatorWith = [
                'amount' => $in,
            ];

            if ((int)($this->transaction->is_type_rate ?? 0) === 1) {
                $calculatorWith['type_rate'] = (int)($this->transaction->type_rate ?? 0);
            }

            if ($at_rate === 3) {
                $calculatorWith['default_rate'] = $manualRateValue;
            }

            if ($at_rate === 4) {
                $calculatorWith['final_adjustment'] = $finalAdjustmentExpression;
            }

            if ($at_rate === 2) {
                // фикс как раньше
                $calculatorWith['final_adjustment'] = '-100';
            }

            // 2) Считаем курс
            try {
                $calculator = CalculatorFacade::setDirectionExchange($this->transaction->direction_exchange)
                    ->setOrder($this->transaction)
                    ->calculateWithOptions($calculatorWith);
            } catch (Throwable $e) {
                Log::error("Ошибка калькуляции заявки #{$this->transaction->id}: {$e->getMessage()}");
                throw $e;
            }

            // 3) Определяем курс (что показываем/сохраняем)
            $newCourse = match ($at_rate) {
                0 => [
                    'amount' => max((float)($this->transaction->course_float ?? 0), 0.0),
                    'curs' => (string)($this->transaction->course_display ?? ''),
                ],
                2 => [
                    'amount' => max((float)($this->transaction->course_float_fixed ?? 0), 0.0),
                    'curs' => (string)($this->transaction->course_display_fixed ?? ''),
                ],
                3, 4 => [
                    'amount' => (float)$calculator->getRateValue(),
                    'curs' => (string)$calculator->getFullRate(),
                ],
                default => [
                    'amount' => (float)$calculator->getRateValue(),
                    'curs' => (string)$calculator->getFullRate(),
                ],
            };

            if (($newCourse['amount'] ?? 0) <= 0) {
                throw new \RuntimeException('Невозможно произвести пересчёт, курс равен нулю.');
            }

            // 4) Комиссии и итоговые суммы
            $calc = new FeeCalculator($this->getDirectionExchange());


            try {
                $responseFee = $calc
                    ->setUser($this->getClient())
                    ->setFromAmount($in)
                    ->withOptions(array_merge($newCourse, [
                        'promo_code' => [
                            'type' => $this->transaction->promo_code_discount_type,
                            'value' => $this->transaction->promo_code_value,
                        ],
                    ]))
                    ->run();
            } catch (Throwable $e) {
                Log::error("Ошибка расчета комиссии заявки #{$this->transaction->id}: {$e->getMessage()}");
                throw $e;
            }

            $incomeDecimal  = (int)($this->getCurrencyIn()->number_format ?? 2);
            $outcomeDecimal = (int)($this->getCurrencyOut()->number_format ?? 2);

            // 5) Обновляем заявку
            $promoId = (int) ($responseFee['id_promo_code'] ?? 0);

            $this->transaction->update([
                'course_display' => $responseFee['course_display'],
                'course_float'   => $responseFee['course_float'],

                'give_price' => iex_number_format($responseFee['give_price'], $incomeDecimal),
                'give_price_default' => iex_number_format($responseFee['give_price_default'], $incomeDecimal),
                'give_price_with_comm' => iex_number_format($responseFee['give_price_with_comm'], $incomeDecimal),
                'give_price_with_comm_pay' => iex_number_format($responseFee['give_price_with_comm_pay'], $incomeDecimal),
                'give_price_fee_comm' => iex_number_format($responseFee['give_price_fee_comm'], $incomeDecimal),
                'give_price_fee_pay' => iex_number_format($responseFee['give_price_fee_pay'], $incomeDecimal),

                'receiving_price' => iex_number_format($responseFee['receiving_price'], $outcomeDecimal),
                'receiving_price_default' => iex_number_format($responseFee['receiving_price_default'], $outcomeDecimal),
                'receiving_price_with_comm' => iex_number_format($responseFee['receiving_price_with_comm'], $outcomeDecimal),
                'receiving_price_with_comm_pay' => iex_number_format($responseFee['receiving_price_with_comm_pay'], $outcomeDecimal),
                'receiving_price_fee_comm' => iex_number_format($responseFee['receiving_price_fee_comm'], $outcomeDecimal),
                'receiving_price_fee_pay' => iex_number_format($responseFee['receiving_price_fee_pay'], $outcomeDecimal),

                'receiving_price_with_promocode' => iex_number_format($responseFee['receiving_price_with_promocode'], $outcomeDecimal),
                'receiving_price_with_user_discount' => iex_number_format($responseFee['receiving_price_with_user_discount'], $outcomeDecimal),
                'receiving_price_user_discount' => iex_number_format($responseFee['receiving_price_user_discount'], $outcomeDecimal),

                'user_discount' => $responseFee['user_discount'],
            ]);

            // 6) Резервы — откатываем/пересчитываем, используя значение ДО пересчёта
            $this->resetReserveAll($oldOut);

            // 7) История — ПОСЛЕ успешного обновления
            // Берём новое значение "получаю" уже из обновлённой заявки
            $fresh = $this->transaction->fresh() ?? $this->transaction;

            $newOut = normalize_amount_to_float((string)$this->getAmountOutFromTask($fresh));

            HistoryRecalculation::create([
                'id_task' => (int)$this->transaction->id,

                // фиксируем изменение "получаю" (out)
                'old_amount' => $oldOut,
                'amount' => $newOut,

                // тип пересчёта
                'type' => $at_rate,

                // курс
                'course' => (string)($newCourse['curs'] ?? ''),
                'course_value' => (string)($newCourse['amount'] ?? '0'),
            ]);

        }, 3);
    }

    /**
     * Возвращает amount out (получаю) из Task после refresh.
     * Если у тебя есть единый метод getAmountOut(), который читает из текущего объекта,
     * то можно заменить реализацию на него. Здесь сделано явно, чтобы избежать путаницы.
     */
    private function getAmountOutFromTask(Task $task): string
    {
        // Если твой getAmountOut() всегда читает из $this->transaction,
        // то после refresh можно просто вызвать (string)$this->getAmountOut().
        // Но чтобы не зависеть от скрытой логики — берём из полей заявки.
        return (string)($task->receiving_price_with_comm
            ?? $task->receiving_price
            ?? '0');
    }

    /**
     * Получаем сумму которую клиент отдает
     *
     * @return float
     */
    public function getAmountIn()
    {
        $give_price = $this->transaction->give_price;
        if (iEXSetting('give_price_type') == 1) {
            $give_price = $this->transaction->give_price_with_comm_pay;
        } elseif (iEXSetting('give_price_type') == 2) {
            $give_price = $this->transaction->give_price_default;
        }

        return $give_price;
    }

    public function getCreditAmount()
    {
        // Сумма, которая должна поступить на счет
        $credit_amount = $this->transaction->give_price;
        $merchant = $this->getMerchant();

        if (isset($merchant)) {
            if ($merchant->credit_amount == 1) {
                $credit_amount = $this->transaction->give_price_with_comm_pay;
            } elseif ($merchant->credit_amount == 2) {
                $credit_amount = $this->transaction->give_price_default;
            }
        }

        return $credit_amount;
    }

    /**
     * Получаем сумму которую клиент желает получить
     */
    public function getAmountOut(): float
    {
        $receiving_price = $this->transaction->receiving_price_with_comm_pay;
        if (iEXSetting('receiving_price_type') == 1) {
            $receiving_price = $this->transaction->receiving_price_with_comm;
        } elseif (iEXSetting('receiving_price_type') == 2) {
            $receiving_price = $this->transaction->receiving_price_default;
        }

        return $receiving_price;
    }

    /**
     * Получение дневного лимита заявок
     *
     * @return bool
     */
    public function dayLimitAmountIn()
    {
        return Task::whereBetween('updated_at', [
            Carbon::today()->startOfDay(),
            Carbon::today()->endOfDay(),
        ])->where('status', '=', 4)
            ->whereHas('direction_exchange', function ($query) {
                $query->where('id_currency1', '=', $this->getCurrencyIn()->id);
            })->sum('give_price');
    }

    /**
     * Получаем номер счета отправителя
     */
    public function getFromShot(): string
    {
        return preg_replace('/\s/', '', $this->transaction->from_shot);
    }

    /**
     * Указываем нового оператора
     */
    public function setOperator(int $scans = 0, bool $isTrash = false): mixed
    {
        if ($isTrash) {
            TaskOperator::where([
                ['id_task', $this->transaction->id],
                ['id_user', Auth::id()],
            ])->delete();
        }

        if ($scans > 0) {
            $response = TaskOperator::create([
                'id_user' => $scans,
                'id_task' => $this->transaction->id,
            ]);
        }

        // Записываем в лог
        TasksOperatorLog::create([
            'id_task' => $this->transaction->id,
            'id_operator' => $scans,
        ]);

        return $response ?? [];
    }

    /**
     * Маск кол-во раз, когда оператора могут сменить
     *
     * @return bool
     */
    public function maxCountChangeOperator()
    {
        return $this->transaction->task_info->count_change_operator >= iEXSetting('count_change_operator', 0);
    }

    /**
     * Отвязываем оператора
     *
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function deleteOperator(): void
    {
        if(in_array($this->getStatus(), [2, 3, 7])) // Разрешается выйти из заявки, только на определенных этапах
        {
            if (\request()->has('actions') and \request()->get('actions') == 'delete_operator' and \auth()->id() == \request()->get('id_delete_operator')) {
                TaskOperator::where([
                    ['id_task', $this->transaction->id],
                    ['id_user', \request()->get('id_delete_operator')],
                ])->delete();
            }
        }
    }

    /**
     * Отправляем сообщение клиенту (чат + уведомления) и/или email (чек).
     *
     * @param string|null $message
     * @param int $type
     */
    public function sendNotification($message = null, int $type = 0): void
    {
        $message = is_string($message) ? trim($message) : '';

        // 1) Если это email/чек — оставляем текущую механику (можно потом вынести в пакет)
        if ($type === 0) {
            if ($message === '') {
                return;
            }

            $input['message_success'] = $message;

            dispatch(new OrderChatJob($this->getClient()->email, $this->transaction, $input))
                ->delay(now()->addMinutes(3))
                ->onQueue('low');

            return;
        }

        // 2) Если это сообщение в чат — заменяем на единый сервис пакета
        if ($type === 1) {
            if ($message === '') {
                return;
            }

            app(OrderChatServiceInterface::class)
                ->adminSendMessage(
                    orderId: (int) $this->transaction->id,
                    managerId: (int) auth()->id(),
                    message: $message
                );

            return;
        }
    }

    /**
     * Отмечаем заявку как непрочитанную
     *
     * @return void
     */
    public function logout()
    {
        $this->transaction->update(['scans' => 0]);
    }

    /**
     * Получаем описание транзакции для оплаты через мерчант
     *
     * @return string
     */
    public function merchantDescription(string $host, string $alias)
    {
        // Индивидуальные описания для каждого мерчанта
        $description = sprintf('Pay_to_%s_%s', $host, $this->transaction->id);
        if (in_array($alias, ['advcash', 'payeer'])) {
            $description = sprintf('Обмен на %s. Заявка #%s', $host, $this->transaction->id);
        } elseif ($alias == 'yandexmoney') {
            $description = sprintf('Перевод личных средств №%s', $this->transaction->id);
        }

        return $description;
    }

    /**
     * Лимит обменов для операторов
     */
    public function limit_operator_view()
    {
        $item = OperationLevel::where([
            ['status', '=', 1],
            ['id_operator', '=', \auth()->id()],
        ])->first();

        if(isset($item) and !empty($item)) {
            $in_amount = convert_to_usd($this->getCodeIn()->name, $this->getAmountIn());
            $min = (float) $item->level_group->from_limit;
            $max = (float) $item->level_group->to_limit;

            return $min <= $in_amount and $max >= $in_amount;
        }

        return 1;
    }
}
