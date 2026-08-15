<?php

namespace App\Services;

use App\Models\Task;
use Carbon\Carbon;
use iEXPackages\Calculator\CalculatorFacade;
use iEXPackages\Transaction\Facades\TransactionFacade;
use Illuminate\Support\Facades\Log;
use Throwable;

class OrderRecalculateService
{
    protected Task $task;
    protected int $currentStatus;
    protected bool $alreadyRecalculated = false;


    /**
     * Конструктор сервиса для пересчета заявок.
     *
     * @param Task $task Заявка для пересчета.
     * @param int|null $currentStatus Текущий статус заявки (если отличается от сохраненного в заявке).
     *
     * @example new OrderRecalculateService($task);
     * @example new OrderRecalculateService($task, 2);
     */
    public function __construct(Task $task, ?int $currentStatus = null, string $context = 'status-change')
    {
        $this->task = $task;
        $this->currentStatus = $currentStatus ?? $task->status;

        $this->processRecalculation($context);
    }

    /**
     * Запускает процесс пересчета в зависимости от типа курса (фиксированный или плавающий).
     */
    protected function processRecalculation(string $context): void
    {
        $recalcType = (int)iEXSetting('type_recalculation_order');

        if ($recalcType === 0 && $context === 'status-change') {
            $this->executeAllRecalculations();
        }

        if ($recalcType === 1 && $context === 'cron') {
            $this->executeAllRecalculations();
        }

        if ($recalcType === 2) {
            $this->executeAllRecalculations();
        }
    }

    /**
     * Выполняет все нужные пересчёты.
     */
    protected function executeAllRecalculations(): void
    {
        if ($this->alreadyRecalculated) {
            return;
        }

        if ($this->task->direction_exchange->is_type_rate === 1 && $this->recountFloating()) {
            $this->alreadyRecalculated = true;
            return;
        }

        if ($this->currencyRecalculate()) {
            $this->alreadyRecalculated = true;
            return;
        }

        if ($this->commonRecalculate()) {
            $this->alreadyRecalculated = true;
        }
    }


    /**
     * Общий пересчет по условиям, заданным в настройках.
     */
    protected function commonRecalculate(): bool
    {
        $allowedStatuses = explode(',', iEXSetting('currency_recount_statusses'));

        if (!in_array($this->currentStatus, $allowedStatuses)) {
            return false;
        }

        $isRecountDefault = (int)iEXSetting('currency_is_recount_default');

        if ($isRecountDefault === 0) {
            return false;
        }

        if ($isRecountDefault === 1) {
            $this->performRecount('Общий постоянный пересчет');
            return true;
        }

        if ($isRecountDefault === 2) {
            $percent = (float)iEXSetting('currency_recount_percent');
            $interval = (int)iEXSetting('currency_recount_time_minutes');

            if ($this->shouldRecountByPercent($percent) || $this->shouldRecountByInterval($interval)) {
                $this->performRecount('Общий пересчет по условиям');
                return true;
            }
        }

        return false;
    }

    /**
     * Пересчет по условиям, заданным отдельно для валюты.
     */
    protected function currencyRecalculate(): bool
    {
        $currency = $this->task->direction_exchange->currency1;

        if (!$currency || !in_array($this->currentStatus, $currency->recount_statusses)) {
            return false;
        }

        $isRecountOrder = (int)$currency->is_enable_auto_recount_order;

        if ($isRecountOrder === 0) {
            return false;
        }

        if ($isRecountOrder === 1) {
            $this->performRecount('Валютный постоянный пересчет');
            return true;
        }

        if ($isRecountOrder === 2) {
            $percent = (float)$currency->unique_recount_percent;
            $interval = (int)$currency->recount_time_minutes;

            if ($this->shouldRecountByPercent($percent) || $this->shouldRecountByInterval($interval)) {
                $this->performRecount('Валютный пересчет по условиям');
                return true;
            }
        }

        return false;
    }

    /**
     * Пересчет заявок с плавающим курсом.
     *
     * Проверяет разницу курсов с учетом плавающих порогов и выполняет пересчет при необходимости.
     */
    protected function recountFloating(): bool
    {
        $direction = $this->task->direction_exchange;

        if ((int)$direction->floating_fee_time <= 0 || $this->task->floating_recount_stop) {
            return false;
        }

        if (!$this->task->task_info?->recalculated_at) {
            return false;
        }

        $nextCheck = $this->task->task_info->recalculated_at->addMinutes($direction->floating_fee_time);

        if (!Carbon::now()->gt($nextCheck)) {
            return false;
        }

        try {
            $calculator = CalculatorFacade::setDirectionExchange($direction)
                ->setOrder($this->task)
                ->calculateWithOptions(['type_rate' => $this->task->type_rate]);

            $newRate = $calculator->getRateValue();
            $currentRate = $this->task->course_float;

            if (!$currentRate || $currentRate <= 0) {
                Log::warning("Некорректный текущий курс заявки #{$this->task->id}");
                return false;
            }

            $percentDiff = round(($newRate / $currentRate - 1) * 100, 4);

            if (
                $percentDiff >= (float)$direction->floating_threshold_recount_up ||
                $percentDiff <= (float)$direction->floating_threshold_recount_down
            ) {
                $this->performRecount('Плавающий пересчет');

                if (
                    $direction->floating_stop_recount_status > 0 &&
                    $this->task->status == $direction->floating_stop_recount_status
                ) {
                    $this->task->update(['floating_recount_stop' => 1]);
                }

                return true; // успешно пересчитали
            } else {
                Log::info("Пересчет плавающего курса не требуется (заявка #{$this->task->id}).");
            }
        } catch (Throwable $e) {
            Log::error("Ошибка плавающего пересчета заявки #{$this->task->id}: {$e->getMessage()}");
        }

        return false;
    }

    /**
     * Проверяет, превышает ли разница курсов установленный процентный порог.
     *
     * @param float $thresholdPercent Пороговое процентное значение для пересчета.
     * @return bool True если пересчет необходим, иначе false.
     *
     * @example $this->shouldRecountByPercent(1.5);
     */
    protected function shouldRecountByPercent(float $thresholdPercent): bool
    {
        if ($thresholdPercent <= 0) {
            return false;
        }

        try {
            $calculator = CalculatorFacade::setDirectionExchange($this->task->direction_exchange)
                ->setOrder($this->task)
                ->calculateWithOptions();

            $newRate = $calculator->getRateValue();
            $oldRate = $this->task->course_float;

            if (!$oldRate || $oldRate <= 0) {
                Log::warning("Некорректный старый курс в заявке #{$this->task->id}, пересчёт отменён.");
                return false;
            }

            $percentDiff = abs(($newRate / $oldRate - 1) * 100);

            return $percentDiff >= $thresholdPercent;
        } catch (Throwable $e) {
            Log::error("Ошибка расчета процента пересчета заявки #{$this->task->id}: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Проверяет, прошло ли достаточно времени с момента последнего пересчета.
     *
     * @param int $intervalMinutes Интервал времени в минутах между пересчетами.
     * @return bool True если пора выполнить пересчет, иначе false.
     *
     * @example $this->shouldRecountByInterval(30);
     */
    protected function shouldRecountByInterval(int $intervalMinutes): bool
    {
        if (!$this->task->task_info || !$this->task->task_info->recalculated_at) {
            return true;
        }

        return Carbon::now()->diffInMinutes($this->task->task_info->recalculated_at) >= $intervalMinutes;
    }

    /**
     * Выполняет пересчет заявки и логирует результат.
     *
     * @param string $reason Причина пересчета (для логирования).
     *
     * @example $this->performRecount('Плавающий пересчет');
     */
    protected function performRecount(string $reason): void
    {
        try {
            $transaction = TransactionFacade::init($this->task);
            $transaction->recount(1, $transaction->getAmountIn());
            $this->task->task_info?->update(['recalculated_at' => Carbon::now()]);

            Log::info("✅ Пересчет заявки #{$this->task->id} выполнен. Причина: {$reason}");
        } catch (Throwable $e) {
            Log::error("Ошибка пересчета заявки #{$this->task->id}: {$e->getMessage()}");
        }
    }
}
