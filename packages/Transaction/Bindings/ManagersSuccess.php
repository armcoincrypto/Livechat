<?php

namespace iEXPackages\Transaction\Bindings;

use App\Events\LiveOrdersUpdatedEvent;
use App\Events\OrderStatusesEvent;
use App\Models\OrderExchangeTotal;
use iEXPackages\SmartMailer\Facades\SmartMailer;
use iEXPackages\SmartMailer\SmartMailerConditionFactory;
use iEXPackages\Transaction\Services\CurrencyAnalyticsService;
use iEXPackages\Transaction\Services\FundInvestmentService;
use iEXPackages\Transaction\Services\MerchantEventService;
use iEXPackages\Transaction\Services\OrderProfitCalculator;
use iEXPackages\Transaction\Services\OrderProfitResultStoreService;
use iEXPackages\Transaction\Services\ProfitCalculationService;
use iEXPackages\Transaction\Services\ReferralBonusService;
use iEXPackages\Transaction\Services\ReserveProfitService;
use iEXPackages\Transaction\Services\UserWalletStoriesService;
use App\Models\Task;
use App\Services\Orders\ManualCompletion\ManualCompletionException;
use App\Services\Orders\ManualCompletion\ManualCompletionGuard;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Trait ManagersSuccess
 *
 * Реализует логику успешного закрытия транзакций и связанных операций.
 */
trait ManagersSuccess
{
    /**
     * Отключает повторное выполнение связанных операций.
     *
     * @var bool
     */
    private bool $disableRelation = false;

    /**
     * Флаг, указывающий, что заявка ожидает выплату (автовыплата отложена)
     */
    private bool $isDeferredAutoPay = false;

    /**
     * Успешное завершение транзакции.
     *
     * @throws \Exception|Throwable
     */
    public function success(array $options = []): void
    {
        if ($options !== []) {
            $this->parameters = array_merge($this->parameters, $options);
        }

        DB::transaction(function () use ($options) {
            $this->lockTaskForCompletion();
            $this->applyCompletionGuard($options);

            $this->validateBeforeComplete();
            $this->disableRelation = true;

            $this->handleCompletedMerchantEvents();

            // --- Новый параметр skip_auto_payment ---
            $skipAutoPayment = (bool)($options['skip_auto_payment'] ?? false);

            // Если не передано skip_auto_payment — запускаем авто-выплату
            if (!$skipAutoPayment && !$this->isDeferredAutoPay) {
                $this->processAutoPayment();
            }

            // Если был включён режим отложенной авто-выплаты — ставим статус "Ожидает выплату"
            if (!$skipAutoPayment && $this->isDeferredAutoPay) {
                if (method_exists($this, 'setChangeStatus')) {
                    $this->setChangeStatus(15);
                } else {
                    $this->setStatus(15);
                }

                $this->disableRelation = false;
                return;
            }

            try {
                app(FundInvestmentService::class)->invest($this->getAmountIn());


                /** @var OrderProfitCalculator $calculator */
                $calculator = app(OrderProfitCalculator::class);

                $result = $calculator->calculateForTask($this->transaction, $this->getAmountIn());

                if ($result !== null) {
                    /** @var OrderProfitResultStoreService $store */
                    $store = app(OrderProfitResultStoreService::class);
                    $store->storeForTask($this->transaction, $result);
                }

                app(ProfitCalculationService::class)->calculateProfit($this->transaction, $this->getAmountIn());
                app(ReferralBonusService::class)->process($this->transaction);
            } catch (Throwable $e) {
                Log::error('Ошибка в TransactionCompletedListener: ' . $e->getMessage(). $e->getLine());
                throw $e;
            }


            $this->storeTransactionMeta();
            $this->storeUserWalletStories();

            $this->updateReserveProfit();

            $this->finalizeTransaction();
            $this->updateAnalytics();



            $telegramId = optional($this->transaction->meta)->telegram_id;
            if (is_numeric($telegramId)) {
                $message = 'Ваша заявка №' . current_order_id($this->transaction) . ' успешно выполнена.';
                $ok = sendTelegramNotification($telegramId, $message);
                 if (!$ok) {
                     Log::warning('Не удалось отправить Telegram-уведомление', [
                         'order_id' => current_order_id($this->transaction),
                         'telegram_id' => $telegramId,
                     ]);
                 }
            }

            // Отсылаем сообщение о завершении заявки
            if (SmartMailerConditionFactory::make('order_completed', $this->transaction)->shouldSend())
            {
                SmartMailer::dispatch(
                    sendable: 'order_completed_job',
                    model: $this->transaction,
                    delaySeconds: 5,
                    queue: 'low'
                );
            }


            $this->converterAmountGive();
        });
    }

    /**
     * Serialize completion against a single locked task row.
     */
    private function lockTaskForCompletion(): void
    {
        $id = (int) ($this->transaction->id ?? 0);
        if ($id <= 0) {
            throw ManualCompletionException::invalidStatus(0);
        }

        $locked = Task::query()->whereKey($id)->lockForUpdate()->first();
        if ($locked === null) {
            throw ManualCompletionException::invalidStatus(0);
        }

        $this->transaction = $locked;
    }

    /**
     * Permission, inbound-status, settlement evidence, same-state idempotency.
     */
    private function applyCompletionGuard(array $options): void
    {
        $source = ManualCompletionGuard::detectSource(array_merge($this->parameters, $options));
        $decision = app(ManualCompletionGuard::class)->authorizeLockedTask(
            $this->transaction,
            $source,
            array_merge($this->parameters, $options),
            auth()->user()
        );

        if ($decision['already_completed'] === true) {
            throw ManualCompletionException::alreadyCompleted();
        }

        $this->parameters['allow_complete_status_write'] = true;
        $this->parameters['manual_settlement'] = $decision['settlement'];
        $this->parameters['completion_from_status'] = $decision['from_status'];
    }

    /**
     * Проверка и валидация перед успешным завершением транзакции.
     *
     * @throws \Exception
     */
    private function validateBeforeComplete(): void
    {
        if ($this->getStatus() === 4) {
            throw new \Exception('Заявка уже была исполнена');
        }

        if ($this->disableRelation) {
            throw new \Exception('Заявка выполняется, пожалуйста подождите...');
        }


        if ($this->isType('merchant')) {
            $codeOrderConfirm = (string)config('security-codes.order-confirm');
            if (Str::length($codeOrderConfirm) > 0 && !Str::of($codeOrderConfirm)->exactly($this->getSecurityOrderCodeConfirm())) {
                throw new \Exception('Неверный пин-код');
            }
        }
    }

    /**
     * Обработка событий, связанных с завершением транзакций мерчанта.
     */
    private function handleCompletedMerchantEvents(): void
    {
        try {
            app(MerchantEventService::class)->handleCompleted($this->transaction);
        } catch (Throwable $e) {
            Log::error('Ошибка MerchantEventService: '.$e->getMessage());
        }
    }

    /**
     * Автоматическая выплата средств при необходимости.
     *
     * @throws \Exception
     */
    private function processAutoPayment(): void
    {
        if ($this->isType('merchant')) {
            if ((int)iEXSetting('max_num_autopay_button') > 0
                && $this->transaction->pay_num >= (int)iEXSetting('max_num_autopay_button')) {
                throw new \Exception('Превышен лимит макс. нажатий "Оплатить и завершить"');
            }

            $this->transaction->increment('pay_num');
            $this->transaction->update(['is_bot' => 0]);

            $autoPay = $this->autoPaymentLoaded();

            if (!empty($autoPay['status']) && $autoPay['status'] === 1) {
                throw new \Exception($autoPay['message']);
            }

            // Если шлюз сообщил, что success нужно отложить — ставим флаг
            if (!empty($autoPay['defer_success'])) {
                $this->isDeferredAutoPay = true;
            }
        }
    }

    /**
     * Сохранение мета-информации транзакции.
     */
    private function storeTransactionMeta(): void
    {
        $opt = $this->transaction->opt_params;
        if (!is_array($opt)) {
            $opt = [];
        }
        $opt['fields'] = $this->getOtherFieldsForSuccess();
        if (!empty($this->parameters['manual_settlement']) && is_array($this->parameters['manual_settlement'])) {
            $opt['manual_settlement'] = $this->parameters['manual_settlement'];
        }

        $this->transaction->update([
            'opt_params' => $opt,
            // Обновляем только один раз — если ещё не установлен
            'id_who_completed' => $this->transaction->id_who_completed > 0
                ? $this->transaction->id_who_completed
                : (auth()->id() ?? 0),
        ]);
    }

    /**
     * Сохранение информации об используемых кошельках пользователя.
     */
    private function storeUserWalletStories(): void
    {
        app(UserWalletStoriesService::class)->storeWallets($this->transaction);
    }

    /**
     * Обновление резервов валют после завершения транзакции.
     */
    private function updateReserveProfit(): void
    {
        if ($this->getReserveIn() && $this->getAmountIn() > 0 && $this->getCurrencyIn()) {
            app(ReserveProfitService::class)->updateReserveAfterSuccess(
                $this->getReserveIn(),
                $this->getAmountIn(),
                $this->getCurrencyIn()
            );
        }
    }

    /**
     * Проверяет, соответствует ли текущая транзакция указанному типу.
     *
     * Безопасно обрабатывает отсутствие параметра 'type',
     * исключая ошибку Undefined array key "type".
     *
     * @param string $expectedType Тип, который нужно проверить (например, 'merchant')
     * @return bool true — если совпадает, иначе false
     */
    private function isType(string $expectedType): bool
    {
        try {
            $type = method_exists($this, 'getType')
                ? (string) $this->getType()
                : ($this->parameters['type'] ?? null);

            return $type !== null && $type === $expectedType;
        } catch (\Throwable $e) {
            Log::warning('Ошибка определения типа транзакции: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Финализация и обновление статуса транзакции.
     */
    private function finalizeTransaction(): void
    {
        $this->setStatus(4);
        $this->disableRelation = false;

        if ($this->transaction && $this->transaction->direction_exchange) {
            $this->transaction->direction_exchange->update([
                'last_order_id' => $this->transaction->id,
                'last_order_at' => Carbon::now(),
                'first_order_id' => $this->transaction->direction_exchange->first_order_id ?: $this->transaction->id,
            ]);
        }

        try {
            LiveOrdersUpdatedEvent::dispatch(['is_live_updated' => 1]);
        } catch (Throwable|BroadcastException $e) {
            Log::warning('Ошибка LiveOrdersUpdatedEvent: '.$e->getMessage());
        }

        if (iEXSetting('is_enabled_module_socket') === 1) {
            broadcast(new OrderStatusesEvent($this->transaction));
        }

        $merchant = $this->getMerchant();
        if ($merchant && $this->getCodeIn()) {
            $merchant->increment('order_num');
            $merchant->increment('total_usd', convert_to_usd($this->getCodeIn()->name, $this->transaction->give_price));
        }
    }

    /**
     * Обновление статистики валют после завершения транзакции.
     */
    private function updateAnalytics(): void
    {
        app(CurrencyAnalyticsService::class)->updateStats($this->transaction, $this->getAmountIn(), $this->getAmountOut());
    }

    /**
     * Конвертирует сумму заявки в USD и сохраняет её в статистику пользователя.
     *
     * @throws \Exception|Throwable
     */
    private function converterAmountGive(): void
    {
        try {
            if (!$this->getCodeIn() || !$this->getCodeOut()) {
                throw new \Exception('Отсутствует информация о валюте');
            }

            if ($this->getAmountIn() <= 0 && $this->getAmountOut() <= 0) {
                throw new \Exception('Суммы для конвертации не указаны');
            }

            $convertByUsd = calculator_converter($this->getCodeIn()->name, 'USD', $this->getAmountIn());

            if ((float)$convertByUsd === 0.0) {
                throw new \Exception('Не удалось конвертировать сумму в USD');
            }

            $formattedUsd = iex_number_format($convertByUsd, 2);

            OrderExchangeTotal::create([
                'id_task' => $this->transaction->id,
                'exchange_usd'  => $formattedUsd,
            ]);

            $this->updateClientExchangeStats((string) $convertByUsd);

        } catch (Throwable $e) {
            Log::error('Ошибка конвертации суммы: ' . $e->getMessage(), ['exception' => $e]);
        }
    }
}
