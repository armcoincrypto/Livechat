<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReserveLedgerAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ReserveLedgerDaily
 *
 * Агрегаты по ledger за день.
 *
 * Зачем нужно:
 * - отчёты/графики/статистика должны работать быстро
 * - читать миллионы строк reserve_ledgers каждый раз — дорого
 *
 * Источник истины:
 * - reserve_ledgers (проводки)
 * - reserve_ledger_daily — витрина/агрегат (может пересчитываться)
 *
 * Важно:
 * - sum_* и closing_balance — DECIMAL -> string (через cast decimal:18)
 * - direction_exchange_id nullable: системные/ручные операции могут быть без направления
 */
class ReserveLedgerDaily extends Model
{
    protected $table = 'reserve_ledger_daily';

    protected $fillable = [
        'day',
        'reserve_id',
        'direction_exchange_id',
        'currency_id',
        'action',

        'count_total',
        'count_in',
        'count_out',

        'sum_delta',
        'sum_in',
        'sum_out',

        'closing_balance',
    ];

    protected $casts = [
        'id' => 'int',
        'day' => 'date',

        'reserve_id' => 'int',
        'direction_exchange_id' => 'int',
        'currency_id' => 'int',

        'count_total' => 'int',
        'count_in' => 'int',
        'count_out' => 'int',

        'sum_delta' => 'decimal:18',
        'sum_in' => 'decimal:18',
        'sum_out' => 'decimal:18',

        'closing_balance' => 'decimal:18',

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

    /* -----------------------------------------------------------------
     | Enum accessor
     |----------------------------------------------------------------- */

    public function getActionEnumAttribute(): ReserveLedgerAction
    {
        return ReserveLedgerAction::from((string) $this->action);
    }

    /* -----------------------------------------------------------------
     | Scopes (для отчётов)
     |----------------------------------------------------------------- */

    public function scopeForReserve(Builder $q, int $reserveId): Builder
    {
        return $q->where('reserve_id', $reserveId);
    }

    public function scopeForDirection(Builder $q, int $directionId): Builder
    {
        return $q->where('direction_exchange_id', $directionId);
    }

    public function scopeForCurrency(Builder $q, int $currencyId): Builder
    {
        return $q->where('currency_id', $currencyId);
    }

    public function scopeForAction(Builder $q, ReserveLedgerAction|string $action): Builder
    {
        $value = $action instanceof ReserveLedgerAction ? $action->value : (string) $action;
        return $q->where('action', $value);
    }

    public function scopeForDay(Builder $q, string $day): Builder
    {
        // YYYY-MM-DD
        return $q->where('day', $day);
    }

    public function scopeBetweenDays(Builder $q, string $fromDay, string $toDay): Builder
    {
        return $q->whereBetween('day', [$fromDay, $toDay]);
    }

    public function scopeForMonth(Builder $q, int $year, int $month): Builder
    {
        return $q->whereYear('day', $year)->whereMonth('day', $month);
    }

    public function scopeLatestFirst(Builder $q): Builder
    {
        return $q->orderByDesc('day');
    }
}
