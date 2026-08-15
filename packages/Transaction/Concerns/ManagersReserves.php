<?php

declare(strict_types=1);

namespace iEXPackages\Transaction\Concerns;

use App\Models\Reserve;
use App\Models\ReserveLedger;
use App\Enums\ReserveLedgerAction;
use App\Enums\ReserveLedgerSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use iEXPackages\Calculator\Traits\InteractsWithNumbers;

/**
 * Управление резервами.
 *
 * Цели:
 * 1) Идемпотентность: один и тот же статус/пересчёт не должен списывать/возвращать дважды.
 * 2) Конкурентная безопасность: параллельные процессы не должны "уводить" reserve->summa.
 * 3) Предсказуемые числа: без экспоненты, с ограниченным scale.
 *
 * Требования:
 * - Cache драйвер должен поддерживать Lock (Redis/Memcached). Для file/array защита слабее.
 * - В таблице reserve_ledgers обязательно держим UNIQUE(idempotency_key) для защиты от дублей.
 */
trait ManagersReserves
{
    use InteractsWithNumbers;

    /**
     * Увеличивает резерв "Отдаю" (IN) — обычно при финальном успешном статусе (например, 4).
     */
    public function updateReserveIn(): void
    {
        // Индивидуальный резерв направления — ничего не делаем
        if ((int) $this->getDirectionExchange()->type_reserve === 1) {
            return;
        }

        $txId = (int) $this->transaction->id;

        // Лочим по заявке, чтобы один и тот же tx не обработался параллельно
        Cache::lock($this->lockKey($txId), 10)->block(3, function (): void {
            DB::transaction(function (): void {
                $reserve = $this->ensureReserveExists($this->getReserveIn(), (int) $this->getCurrencyIn()->id);

                if ((int) ($reserve->is_fixed_reserve ?? 0) === 1) {
                    return;
                }

                // Блокируем строку резерва, чтобы не было гонок
                $reserve = Reserve::query()->whereKey($reserve->id)->lockForUpdate()->firstOrFail();

                $scale = $this->reserveScale((int) ($this->getCurrencyIn()->number_format ?? 10));
                $delta = $this->toBcString($this->getAmountIn(), $scale);

                $from = $this->toBcString($reserve->summa, $scale);
                $to = $this->mathAdd($from, $delta, $scale);

                if ($this->mathEquals($from, $to, $scale)) {
                    return;
                }

                // Идемпотентный лог (один на tx+reserve+type)
                $this->writeLedger(
                    action: ReserveLedgerAction::COMMIT_IN,
                    reserveId: (int) $reserve->id,
                    directionId: (int) $this->getDirectionExchange()->id,
                    taskId: (int) $this->transaction->id,
                    currencyId: (int) $this->getCurrencyIn()->id,
                    sourceType: ReserveLedgerSource::TASK,
                    sourceId: (int) ($this->transaction->id_user ?? 0),
                    delta: $delta,
                    before: $from,
                    after: $to,
                    meta: [
                        'scale' => $scale,
                        'side' => 'in',
                        'status' => (int) ($this->transaction->status ?? 0),
                    ]
                );

                $reserve->update(['summa' => $to]);
            }, 3);
        });
    }

    /**
     * Уменьшает резерв "Получаю" (OUT) — обычно при переходе в статус "в работе" (например, 3/7).
     *
     * ВАЖНО:
     * - $newAmount > 0 используется при пересчёте: сначала возвращаем старую сумму ($oldAmount),
     *   затем списываем новую ($newAmount).
     */
    public function updateReserveOut(float $newAmount = 0, float $oldAmount = 0): void
    {
        // Индивидуальный резерв направления — ничего не делаем
        if ((int) $this->getDirectionExchange()->type_reserve === 1) {
            return;
        }

        $txId = (int) $this->transaction->id;

        Cache::lock($this->lockKey($txId), 10)->block(3, function () use ($newAmount, $oldAmount): void {
            DB::transaction(function () use ($newAmount, $oldAmount): void {
                // Пересчёт: вернуть старое (один раз), затем списать новое
                if ($newAmount > 0) {
                    $this->releaseReserveOut($oldAmount);
                }

                $reserve = $this->ensureReserveExists($this->getReserveOut(), (int) $this->getCurrencyOut()->id);

                if ((int) ($reserve->is_fixed_reserve ?? 0) === 1) {
                    return;
                }

                $reserve = Reserve::query()->whereKey($reserve->id)->lockForUpdate()->firstOrFail();

                $scale = $this->reserveScale((int) ($this->getCurrencyOut()->number_format ?? 10));
                $delta = $this->toBcString(($newAmount > 0 ? $newAmount : $this->getAmountOut()), $scale);

                $from = $this->toBcString($reserve->summa, $scale);
                $to = $this->mathSub($from, $delta, $scale);

                if ($this->mathEquals($from, $to, $scale)) {
                    return;
                }

                // delta для HOLD_OUT делаем отрицательным
                $deltaSigned = $this->mathSub('0', $delta, $scale);

                $this->writeLedger(
                    action: ReserveLedgerAction::HOLD_OUT,
                    reserveId: (int) $reserve->id,
                    directionId: (int) $this->getDirectionExchange()->id,
                    taskId: (int) $this->transaction->id,
                    currencyId: (int) $this->getCurrencyOut()->id,
                    sourceType: ReserveLedgerSource::TASK,
                    sourceId: (int) ($this->transaction->id_user ?? 0),
                    delta: $deltaSigned,
                    before: $from,
                    after: $to,
                    meta: [
                        'scale' => $scale,
                        'side' => 'out',
                        'status' => (int) ($this->transaction->status ?? 0),
                        'recount' => $newAmount > 0,
                        'old_amount' => $newAmount > 0 ? $oldAmount : null,
                    ]
                );

                $reserve->update(['summa' => $to]);
            }, 3);
        });
    }

    /**
     * Полный "сброс" резервов для заявки: безопасно возвращает OUT-резерв назад.
     *
     * Старый код делал рекурсивный вызов updateReserveOut() → легко получить двойные состояния.
     * Здесь: только возврат (releaseReserveOut). Ledger хранит историю и не очищается.
     */
    public function resetReserveAll(float $amount = 0): void
    {
        // Индивидуальный резерв направления — ничего не делаем
        if ((int) $this->getDirectionExchange()->type_reserve === 1) {
            return;
        }

        $txId = (int) $this->transaction->id;

        Cache::lock($this->lockKey($txId), 10)->block(3, function () use ($amount): void {
            DB::transaction(function () use ($amount): void {
                // Возврат средств в OUT-резерв
                $this->releaseReserveOut($amount);
            }, 3);
        });
    }

    /**
     * Возврат суммы обратно в OUT-резерв (используется при отмене/пересчёте).
     */
    protected function releaseReserveOut(float $amount = 0): void
    {
        $reserve = $this->ensureReserveExists($this->getReserveOut(), (int) $this->getCurrencyOut()->id);

        if ((int) ($reserve->is_fixed_reserve ?? 0) === 1) {
            return;
        }

        $reserve = Reserve::query()->whereKey($reserve->id)->lockForUpdate()->firstOrFail();

        $scale = $this->reserveScale((int) ($this->getCurrencyOut()->number_format ?? 10));
        $delta = $this->toBcString(($amount > 0 ? $amount : $this->getAmountOut()), $scale);

        $from = $this->toBcString($reserve->summa, $scale);
        $to = $this->mathAdd($from, $delta, $scale);

        if ($this->mathEquals($from, $to, $scale)) {
            return;
        }

        $this->writeLedger(
            action: ReserveLedgerAction::RELEASE_OUT,
            reserveId: (int) $reserve->id,
            directionId: (int) $this->getDirectionExchange()->id,
            taskId: (int) $this->transaction->id,
            currencyId: (int) $this->getCurrencyOut()->id,
            sourceType: ReserveLedgerSource::TASK,
            sourceId: (int) ($this->transaction->id_user ?? 0),
            delta: $delta,
            before: $from,
            after: $to,
            meta: [
                'scale' => $scale,
                'side' => 'out',
                'status' => (int) ($this->transaction->status ?? 0),
                'reason' => 'release',
            ]
        );

        $reserve->update(['summa' => $to]);
    }

    /**
     * Создаёт резерв, если его нет. Учитывает объединённый резерв, т.к. getReserveIn/Out уже его возвращает.
     */
    protected function ensureReserveExists(?Reserve $reserve, int $currencyId): Reserve
    {
        if ($reserve instanceof Reserve) {
            return $reserve;
        }

        return Reserve::updateOrCreate(
            ['id_currency' => $currencyId],
            [
                'id_currency' => $currencyId,
                'id_group' => 0,
                'summa' => 0,
                'id_user' => 0,
            ]
        );
    }

    /**
     * Ключ блокировки для заявки.
     */
    protected function lockKey(int $txId): string
    {
        return 'iex:reserve:tx:' . $txId;
    }

    /**
     * Ограниченный scale для резервов (на всякий случай).
     */
    protected function reserveScale(int $scale): int
    {
        $max = (int) iEXSetting('max_number_format_reserve', 10);

        return max(0, min($scale, $max));
    }


    /**
     * Генерирует стабильный ключ идемпотентности.
     *
     * ВАЖНО: ключ зависит от action, reserve, task и delta (чтобы разные пересчёты не схлопывались).
     */
    protected function ledgerKey(ReserveLedgerAction $action, int $reserveId, int $taskId, string $delta): string
    {
        $base = implode('|', [
            $action->value,
            'r' . $reserveId,
            't' . $taskId,
            // delta включаем, чтобы разные операции по одной заявке не схлопывались
            $delta,
        ]);

        // До 120 символов, читаемо и уникально
        return $action->value . ':' . substr(sha1($base), 0, 80);
    }

    /**
     * Запись проводки в ledger (идемпотентно по idempotency_key).
     * Вызывать внутри DB::transaction и после lockForUpdate() резерва.
     */
    protected function writeLedger(
        ReserveLedgerAction $action,
        int $reserveId,
        int $directionId,
        int $taskId,
        int $currencyId,
        ReserveLedgerSource $sourceType,
        int $sourceId,
        string $delta,
        string $before,
        string $after,
        array $meta = [],
    ): void {
        $key = $this->ledgerKey($action, $reserveId, $taskId, $delta);

        ReserveLedger::updateOrCreate(
            ['idempotency_key' => $key],
            [
                'reserve_id' => $reserveId,
                'direction_exchange_id' => $directionId,
                'task_id' => $taskId,
                'currency_id' => $currencyId,

                'action' => $action->value,
                'source_type' => $sourceType->value,
                'source_id' => $sourceId,

                'delta' => $delta,
                'balance_before' => $before,
                'balance_after' => $after,

                'meta' => $meta,
                'occurred_at' => Carbon::now(),
            ]
        );
    }
}
