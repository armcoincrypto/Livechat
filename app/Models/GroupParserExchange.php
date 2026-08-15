<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * GroupParserExchange
 *
 * Назначение:
 * - Хранит группу парсеров (alias) и её настройки.
 * - Хранит статистику последнего обновления группы (для админки/мониторинга).
 *
 * Статистика обновления:
 * - last_updated_at    datetime|null — когда группа обновлялась последний раз
 * - last_duration_ms   int           — длительность последнего обновления (мс)
 * - last_total         int           — сколько пар в группе было обработано
 * - last_updated       int           — сколько пар обновилось успешно
 * - last_errors        int           — сколько пар не обновилось / ошибок
 *
 * Вычисляемое поле:
 * - last_success_percent int — успешность обновления (0..100)
 *
 * Backward compatibility:
 * - Оставлены старые методы parser_exchange_enabled() и parser_exchange(...)
 *   чтобы не ломать старый код.
 */
class GroupParserExchange extends Model
{
    protected $table = 'group_parser_exchange';

    /**
     * Массовое заполнение.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'status',
        'sorting',
        'alias',
        'provider_id',
        'proxy_id',
        'last_updated_at',
        'last_imported_at',
        'is_import_rates',
        'is_delete',

        'last_duration_ms',
        'last_total',
        'last_updated',
        'last_errors',
    ];

    /**
     * Касты для корректного JSON/API и удобной работы в PHP.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => 'integer',
        'sorting' => 'integer',
        'provider_id' => 'integer',
        'proxy_id' => 'integer',
        'is_import_rates' => 'integer',
        'is_delete' => 'integer',

        'last_updated_at' => 'datetime',
        'last_imported_at' => 'datetime',

        'last_duration_ms' => 'integer',
        'last_total' => 'integer',
        'last_updated' => 'integer',
        'last_errors' => 'integer',
    ];

    /**
     * Все пары источника (без фильтра по status).
     */
    public function parserExchange(): HasMany
    {
        return $this->hasMany(ParserExchange::class, 'id_group', 'id');
    }

    /**
     * Только активные пары источника (status=1).
     */
    public function parserExchangeEnabled(): HasMany
    {
        return $this->hasMany(ParserExchange::class, 'id_group', 'id')
            ->where('status', 1);
    }

    /**
     * Legacy: только активные пары источника (status=1).
     *
     * Оставлено для обратной совместимости со старым кодом.
     * В новом коде используйте parserExchangeEnabled().
     */
    public function parser_exchange_enabled(): HasMany
    {
        return $this->parserExchangeEnabled();
    }

    /**
     * Legacy: активные пары источника с фильтрами.
     *
     * Оставлено для обратной совместимости.
     * В новом коде лучше использовать parserExchangeEnabled() + дополнительные where() снаружи.
     *
     * @param int $id_group Старый параметр (по факту не нужен, т.к. связь уже ограничена group->id)
     * @param string|null $pair Фильтр по имени пары (например "USD - RUB")
     * @param bool $orderBy Сортировать по type
     */
    public function parser_exchange(int $id_group = 0, ?string $pair = null, bool $orderBy = false): HasMany
    {
        $q = $this->hasMany(ParserExchange::class, 'id_group', 'id')
            ->where('status', 1);

        // Оставляем, чтобы старые вызовы не ломались (хотя условие избыточно)
        if ($id_group > 0) {
            $q->where('id_group', $id_group);
        }

        if ($pair !== null && $pair !== '') {
            $q->where('name', $pair);
        }

        if ($orderBy) {
            $q->orderBy('type');
        }

        return $q;
    }

    /**
     * Процент успешности последнего обновления (0..100).
     *
     * Удобно отдавать в API без вычислений в контроллере/фронте.
     */
    public function getLastSuccessPercentAttribute(): int
    {
        $total = (int) ($this->last_total ?? 0);
        $updated = (int) ($this->last_updated ?? 0);

        if ($total <= 0) {
            return 0;
        }

        $percent = (int) round(100 * $updated / $total);

        if ($percent < 0) {
            return 0;
        }

        if ($percent > 100) {
            return 100;
        }

        return $percent;
    }
}
