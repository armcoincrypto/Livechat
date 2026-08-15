<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReserveLedgerAction;
use App\Enums\ReserveLedgerSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ReserveLedger
 *
 * Журнал проводок по резервам (ledger).
 *
 * Важные принципы:
 * - reserve_ledgers — источник истины для истории и аналитики
 * - reserves.summa — текущее состояние (снимок)
 * - delta хранит изменение (+/-) в DECIMAL(65,18)
 * - idempotency_key UNIQUE гарантирует отсутствие дублей даже при гонках
 * - meta хранит любые доп. данные (курс, комиссия, причина, ip, gateway, оператор и т.д.)
 *
 * Типы ID под твою БД:
 * - reserves.id = int (signed)         -> reserve_id: int
 * - direction_exchange.id = int (signed)-> direction_exchange_id: int|null
 * - tasks.id = bigint unsigned         -> task_id: bigint|null (в модели хранится как int, но в БД unsignedBigInteger)
 */
class ReserveLedger extends Model
{
    protected $table = 'reserve_ledgers';

    /**
     * Массовое заполнение.
     * ВАЖНО: delta/balance_* — строки (decimal cast), не float.
     */
    protected $fillable = [
        'reserve_id',
        'direction_exchange_id',
        'task_id',
        'currency_id',

        'action',
        'source_type',
        'source_id',

        'delta',
        'balance_before',
        'balance_after',

        'idempotency_key',
        'meta',
        'occurred_at',
    ];

    /**
     * Касты.
     * DECIMAL -> string, чтобы не было float-ошибок и scientific notation.
     */
    protected $casts = [
        'id' => 'int',
        'reserve_id' => 'int',
        'direction_exchange_id' => 'int',
        'task_id' => 'int',
        'currency_id' => 'int',
        'source_id' => 'int',

        'delta' => 'decimal:18',
        'balance_before' => 'decimal:18',
        'balance_after' => 'decimal:18',

        'meta' => 'array',
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /* -----------------------------------------------------------------
     | Relations
     |----------------------------------------------------------------- */

    public function reserve(): BelongsTo
    {
        return $this->belongsTo(Reserve::class, 'reserve_id');
    }

    public function directionExchange(): BelongsTo
    {
        return $this->belongsTo(DirectionExchange::class, 'direction_exchange_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    /* -----------------------------------------------------------------
     | Enum accessors (удобно для типобезопасности)
     |----------------------------------------------------------------- */

    /**
     * Вернуть action как enum.
     */
    public function getActionEnumAttribute(): ReserveLedgerAction
    {
        return ReserveLedgerAction::from((string) $this->action);
    }

    /**
     * Вернуть source_type как enum.
     */
    public function getSourceEnumAttribute(): ReserveLedgerSource
    {
        return ReserveLedgerSource::from((string) $this->source_type);
    }

    /* -----------------------------------------------------------------
     | Scopes (для аналитики/отчётов)
     |----------------------------------------------------------------- */

    public function scopeForReserve(Builder $q, int $reserveId): Builder
    {
        return $q->where('reserve_id', $reserveId);
    }

    public function scopeForTask(Builder $q, int $taskId): Builder
    {
        return $q->where('task_id', $taskId);
    }

    public function scopeForDirection(Builder $q, int $directionId): Builder
    {
        return $q->where('direction_exchange_id', $directionId);
    }

    /**
     * Фильтр по action через enum (предпочтительно).
     */
    public function scopeForAction(Builder $q, ReserveLedgerAction|string $action): Builder
    {
        $value = $action instanceof ReserveLedgerAction ? $action->value : (string) $action;
        return $q->where('action', $value);
    }

    /**
     * Фильтр по source через enum (предпочтительно).
     */
    public function scopeForSource(Builder $q, ReserveLedgerSource|string $source): Builder
    {
        $value = $source instanceof ReserveLedgerSource ? $source->value : (string) $source;
        return $q->where('source_type', $value);
    }

    /**
     * Период по occurred_at.
     * Принимает ISO или YYYY-MM-DD.
     */
    public function scopeBetweenDates(Builder $q, string $from, string $to): Builder
    {
        return $q->whereBetween('occurred_at', [$from, $to]);
    }

    /**
     * За конкретный день (по occurred_at).
     */
    public function scopeForDay(Builder $q, string $day): Builder
    {
        // day: YYYY-MM-DD
        return $q->whereDate('occurred_at', $day);
    }

    /**
     * За месяц (по occurred_at) — удобно, раз monthly таблицу не делаем.
     */
    public function scopeForMonth(Builder $q, int $year, int $month): Builder
    {
        return $q->whereYear('occurred_at', $year)->whereMonth('occurred_at', $month);
    }

    public function scopeLatestFirst(Builder $q): Builder
    {
        return $q->orderByDesc('id');
    }
}
