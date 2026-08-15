<?php
declare(strict_types=1);

namespace iEXPackages\Transaction\Bindings;

use App\Events\OrderStatusesEvent;
use iEXPackages\SmartMailer\Facades\SmartMailer;
use iEXPackages\SmartMailer\SmartMailerConditionFactory;
use iEXPackages\Transaction\Services\MerchantEventService;
use Illuminate\Support\Facades\Log;
use Psr\SimpleCache\InvalidArgumentException;
use Throwable;

/**
 * Trait ManagersReject
 *
 * Реализует логику отклонения заявок и связанных проверок на мошенничество.
 */
trait ManagersReject
{
    /** @var int Статус "Отклонено" */
    private const STATUS_REJECTED = 5;

    /** @var int Категория отклонения "Мошенничество" */
    private const CATEGORY_SCAM = 4;

    /**
     * Отклоняет заявку с выполнением всех проверок и дополнительных действий.
     *
     * @throws InvalidArgumentException|Throwable
     */
    public function reject(): void
    {
        $this->validateBeforeReject();

        $this->setStatus(self::STATUS_REJECTED);

        $this->handleMerchantCanceledEvent();
        $this->broadcastOrderStatus();
        $this->handleScamOrderActions();


        $telegramId = optional($this->transaction->meta)->telegram_id;

        if (is_numeric($telegramId)) {
            $message  = 'Ваша заявка №' . current_order_id($this->transaction) . ' была отклонена.' . "\n";

            $reason = optional($this->transaction->tasks_rejection_status)->name;

            if (is_string($reason) && trim($reason) !== '') {
                $message .= 'Причина: ' . $reason;
            } else {
                $message .= 'Причина не указана.';
            }

            sendTelegramNotification($telegramId, $message);
        }

        // Отсылаем сообщение об отмене заявки
        if (SmartMailerConditionFactory::make('order_rejected', $this->transaction)->shouldSend())
        {
            SmartMailer::dispatch(
                sendable: 'order_rejected_job',
                model: $this->transaction,
                delaySeconds: 5,
                queue: 'low'
            );
        }

        Log::info(sprintf(
            'Заявка №%d отклонена менеджером #%s',
            $this->transaction->id,
            auth()->id() ?? 'system'
        ));
    }

    /**
     * Выполняет предварительные проверки перед отклонением заявки.
     *
     * @throws \Exception
     */
    private function validateBeforeReject(): void
    {
        if (!$this->transaction) {
            throw new \Exception('Транзакция не определена.');
        }

        if ($this->getStatus() === self::STATUS_REJECTED) {
            throw new \Exception('Транзакция уже была отклонена.');
        }
    }

    /**
     * Обрабатывает событие отмены у внешнего мерчанта.
     */
    private function handleMerchantCanceledEvent(): void
    {
        try {
            app(MerchantEventService::class)->handleRejected($this->transaction);
        } catch (Throwable $e) {
            Log::error('Ошибка при обработке отмены мерчанта: ' . $e->getMessage(), [
                'transaction_id' => $this->transaction->id,
                'exception' => $e,
            ]);
        }
    }

    /**
     * Транслирует обновление статуса заявки через сокеты.
     */
    private function broadcastOrderStatus(): void
    {
        if ((int)iEXSetting('is_enabled_module_socket') === 1) {
            broadcast(new OrderStatusesEvent($this->transaction));
        }
    }

    /**
     * Выполняет дополнительные действия при мошеннической заявке (бан, чёрный список).
     */
    private function handleScamOrderActions(): void
    {
        // Не категория «мошенничество» — никаких спец-действий не нужно
        if ($this->getCategoryReject() !== self::CATEGORY_SCAM) {
            return;
        }

        $transactionId = $this->transaction->id ?? null;

        // Добавляем реквизиты клиента в чёрный список, если модуль включён
        if ((int) iEXSetting('allow_order_blacklist') === 1) {
            $this->clientToBlackList();

            Log::info('Клиент добавлен в чёрный список за мошенническую заявку', [
                'transaction_id' => $transactionId,
                'user_id'        => optional($this->getClient())->id,
            ]);
        }

        // Автобан за мошенническую заявку (вся логика ступеней — внутри clientBan)
        if ((int) iEXSetting('auto_ban_for_scam_order') === 1) {
            $reason = optional($this->transaction->tasks_rejection_status)->name;

            $this->clientBan([
                'comment' => sprintf(
                    'Заявка №%s признана мошеннической%s',
                    current_order_id($this->transaction),
                    $reason ? sprintf(' (%s)', $reason) : ''
                ),
            ]);

            Log::warning('Клиент автоматически забанен за мошенническую заявку', [
                'transaction_id' => $transactionId,
                'user_id'        => optional($this->getClient())->id,
                'reason'         => $reason,
            ]);
        }
    }
}
