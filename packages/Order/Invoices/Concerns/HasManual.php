<?php

namespace iEXPackages\Order\Invoices\Concerns;

use App\Models\Requisites;
use App\Models\Task;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;


trait HasManual
{
    /**
     * Получает актуальные реквизиты для ручного кошелька.
     *
     * @return array Возвращает массив с номером кошелька или пустой массив.
     */
    protected function getManualWallet(): array
    {
        // Валюта может быть не загружена в некоторых сценариях — защищаемся
        if (!$this->currencyIn || !$this->currencyIn->id) {
            return [];
        }

        $wallets = Requisites::activeWallet()
            ->where('id_currency', $this->currencyIn->id)
            ->when((int)($this->currencyIn->type_output_requisites ?? 0) === 1, fn($q) => $q->inRandomOrder())
            ->when((int)($this->currencyIn->type_output_requisites ?? 0) === 2, fn($q) => $q->orderBy('id', 'asc'))

            ->where(function ($query) {
                $query->where('is_unique_shot', 0)
                    ->orWhere(fn($q) => $q->where('is_unique_shot', 1)->where('is_already_used', 0));
            })
            ->get();

        return $this->fetchManualShot($wallets, 'currency');
    }

    /**
     * Получает актуальные реквизиты для ручного направления обмена.
     *
     * @param Collection $directionExchangeRequest Коллекция с реквизитами направлений обмена
     * @return array Возвращает массив с номером счета или пустой массив.
     */
    protected function getDirectionExchangeManual(Collection $directionExchangeRequest): array
    {
        return $this->fetchManualShot($directionExchangeRequest, 'direction');
    }

    /**
     * Извлекает подходящие реквизиты с проверкой лимитов и обновлением счетчиков.
     *
     * @param Collection $requisites
     * @param string $typeRequisite
     * @return array Возвращает массив с номером счета или пустой массив.
     *
     */
    protected function fetchManualShot(Collection $requisites, string $typeRequisite): array
    {
        // Определяем модель источника: для направления — DirectionRequisite, для валюты — Requisites
        $modelClass = $typeRequisite === 'direction'
            ? \App\Models\DirectionRequisite::class
            : \App\Models\Requisites::class;

        /** @var mixed $requisite */
        $requisite = $requisites
            ->filter(fn($value) => !empty($value->account_number))
            ->filter(function ($value) use ($typeRequisite) {
                if ($typeRequisite === 'direction') {
                    // У DirectionRequisite нет флагов одноразовости — пропускаем
                    return true;
                }
                // Для Requisites учитываем одноразовость
                return (int)$value->is_unique_shot === 0
                    || ((int)$value->is_unique_shot === 1 && (int)$value->is_already_used === 0);
            })
            ->first(fn($value) => !$this->exceedsLimit($value, $typeRequisite));

        if (!$requisite) {
            return [];
        }

        DB::transaction(function () use (&$requisite, $typeRequisite, $modelClass): void {
            // 1) Блокируем реквизит
            /** @var \Illuminate\Database\Eloquent\Model|null $locked */
            $locked = $modelClass::query()
                ->whereKey($requisite->id)
                ->lockForUpdate()
                ->first();

            if (!$locked) {
                throw new \RuntimeException('Реквизит не найден при блокировке.');
            }

            // 2) Блокируем заявку (чтобы её не перезаписали параллельно)
            /** @var \App\Models\Task|null $task */
            $task = \App\Models\Task::query()
                ->whereKey($this->task->id)
                ->lockForUpdate()
                ->first();

            if (!$task) {
                throw new \RuntimeException('Заявка не найдена при блокировке.');
            }

            // 3) Повторная проверка под блокировкой (только для валютных реквизитов)
            if ($typeRequisite !== 'direction') {
                $isUnique    = (int)($locked->is_unique_shot ?? 0) === 1;
                $alreadyUsed = (int)($locked->is_already_used ?? 0) === 1;

                if (($isUnique && $alreadyUsed) || $this->exceedsLimit($locked, $typeRequisite)) {
                    throw new \RuntimeException('Реквизит недоступен: превышен лимит или уже использован.');
                }

                // Счётчик просмотров
                if (isset($locked->view)) {
                    $locked->increment('view');
                }

                // Одноразовый — помечаем использованным
                if ($isUnique) {
                    $locked->forceFill(['is_already_used' => 1])->save();
                }
            }

            // 4) Защита от тихого перезаписывания (если уже назначено — не трогаем)
            if (!empty($task->id_payment_requisites) || !empty($task->transfer_to_account)) {
                $requisite = $locked;
                return;
            }

            // 5) Обновляем заявку
            $updated = $task->forceFill([
                'id_payment_requisites'     => (int)$locked->getKey(),
                'transfer_to_account'       => (string)($locked->account_number ?? ''),
                'transfer_to_account_type'  => 'manual',
                'type_requisite'            => $typeRequisite,
            ])->save();

            if (!$updated) {
                throw new \RuntimeException('Не удалось обновить заявку (task->save вернул false).');
            }

            // 6) Возвращаем актуально зафиксированный реквизит наружу
            $this->task = $task;
            $requisite  = $locked;
        });

        return ['account' => (string) $requisite->account_number];
    }


    /**
     * Проверка лимитов реквизитов (дневной, месячный, просмотры).
     *
     * @param object $value
     * @param string $typeRequisite
     * @return bool
     */
    protected function exceedsLimit(object $value, string $typeRequisite): bool
    {
        if ((int)$value->limit_views > 0 && (int)$value->view >= (int)$value->limit_views) {
            return true;
        }

        return $this->hasExceededAmountLimit($value, $typeRequisite);
    }

    /**
     * Проверка превышения суммы по лимитам за периоды.
     *
     * @param object $value
     * @param string $typeRequisite
     * @return bool
     */
    private function hasExceededAmountLimit(object $value, string $typeRequisite): bool
    {
        $accountNumber = $value->account_number;

        if ($this->hasAmountExceededForPeriod($accountNumber, $value->limit_day, Carbon::today(), $typeRequisite)) {
            return true;
        }

        if ($this->hasAmountExceededForPeriod($accountNumber, $value->limit_month, Carbon::today()->startOfMonth(), $typeRequisite, Carbon::today()->endOfMonth())) {
            return true;
        }

        return false;
    }


    /**
     * Проверка суммы по полю transfer_to_account и типу реквизита.
     *
     * @param ?string $accountNumber
     * @param float|null $limit
     * @param Carbon $start
     * @param string $typeRequisite
     * @param Carbon|null $end
     * @return bool
     */
    private function hasAmountExceededForPeriod(?string $accountNumber, ?float $limit, Carbon $start, string $typeRequisite, Carbon $end = null): bool
    {
        if ($limit === null || $limit <= 0 || empty($accountNumber)) {
            return false;
        }

        $end ??= $start->copy()->endOfDay();

        // Суммируем точно; БД обычно возвращает DECIMAL строкой — приводим к строке
        $sum = Task::where('transfer_to_account', $accountNumber)
            ->where('type_requisite', $typeRequisite)
            ->whereIn('status', [3, 4])
            ->whereBetween('updated_at', [$start, $end])
            ->sum('give_price');

        try {
            $total = BigDecimal::of((string) $sum);
            $limitDec = BigDecimal::of((string) $limit);
            return $total->compareTo($limitDec) >= 0;
        } catch (\Throwable $e) {
            // В случае проблем с форматом — перестраховка: считаем, что лимит не превышен
            return false;
        }
    }
}
