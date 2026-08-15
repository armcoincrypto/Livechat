<?php
declare(strict_types=1);

namespace App\Models;

use App\Models\Filters\OrderStatusLogFilter;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class TaskStatusLog extends Model
{
    use Filterable;
    use SoftDeletes;

    protected $table = 'tasks_status_log';

    /**
     * Источник изменения статуса (place_change)
     */
    public const PLACE_SITE     = 0;
    public const PLACE_ADMIN    = 1;
    public const PLACE_MERCHANT = 2;
    public const PLACE_SYSTEM   = 3;

    protected $fillable = [
        'id_task',
        'id_direction_exchange',
        'user_id',

        'old_status',
        'new_status',

        // старые поля (совместимость)
        'in_price',
        'out_price',
        'course_display',

        // новые поля (нормализованные)
        'course_display_text',
        'in_amount',
        'out_amount',
        'course_display_rate',
        'course_float_rate',
        'course_diff_percent',

        'place_change',
    ];

    protected $casts = [
        'id_task' => 'integer',

        // ВАЖНО: direction_exchange.id = SIGNED INT → здесь тоже integer (не unsigned)
        'id_direction_exchange' => 'integer',

        'user_id' => 'integer',

        'old_status' => 'integer',
        'new_status' => 'integer',

        'place_change' => 'integer',

        // DECIMAL кастим в строку с форматированием
        'in_amount' => 'decimal:18',
        'out_amount' => 'decimal:18',
        'course_display_rate' => 'decimal:18',
        'course_float_rate' => 'decimal:18',
        'course_diff_percent' => 'decimal:6',

        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function modelFilter(): ?string
    {
        return $this->provideFilter(OrderStatusLogFilter::class);
    }

    /**
     * Лог принадлежит заявке
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'id_task', 'id');
    }

    /**
     * Направление на момент смены статуса (snapshot)
     */
    public function directionExchange(): BelongsTo
    {
        return $this->belongsTo(DirectionExchange::class, 'id_direction_exchange', 'id');
    }

    /**
     * Кто изменил (если есть)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function oldStatus(): BelongsTo
    {
        return $this->belongsTo(TaskStatus::class, 'old_status', 'id');
    }

    public function newStatus(): BelongsTo
    {
        return $this->belongsTo(TaskStatus::class, 'new_status', 'id');
    }

    /**
     * Скоупы
     */
    public function scopeForTask(Builder $query, int $taskId): Builder
    {
        return $query->where('id_task', $taskId);
    }

    public function scopeForDirection(Builder $query, int $directionId): Builder
    {
        return $query->where('id_direction_exchange', $directionId);
    }

    public function scopeStatusChangedTo(Builder $query, int $statusId): Builder
    {
        return $query->where('new_status', $statusId);
    }

    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('id');
    }

    /**
     * place_change сейчас не используешь — метод можно оставить, не мешает.
     */
    public function getPlaceLabelAttribute(): string
    {
        return match ((int) $this->place_change) {
            self::PLACE_ADMIN => 'Админ-панель',
            self::PLACE_MERCHANT => 'Мерчант',
            self::PLACE_SYSTEM => 'Система',
            default => 'Сайт',
        };
    }
}
