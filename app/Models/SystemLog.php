<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $module
 * @property string|null $name
 * @property string $level
 * @property string $state          // 'active'|'resolved'
 * @property string|null $entity_type
 * @property string|null $entity_id
 * @property string|null $code
 * @property int|null $active_flag
 * @property string $message
 * @property array|null $context
 * @property array|null $source
 * @property \Illuminate\Support\Carbon|null $occurred_at
 * @property string|null $dedup_hash
 * // episode fields:
 * @property \Illuminate\Support\Carbon|null $first_seen_at
 * @property \Illuminate\Support\Carbon|null $last_seen_at
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property int $times_seen
 * @property string|null $last_message
 * @property array|null $last_context
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class SystemLog extends Model
{
    use HasFactory;

    protected $table = 'system_logs';

    protected $fillable = [
        'module',
        'name',
        'level',
        'state',
        'entity_type',
        'entity_id',
        'code',
        'message',
        'context',
        'source',
        'occurred_at',
        'dedup_hash',
        'active_flag',
        // episode fields
        'first_seen_at',
        'last_seen_at',
        'resolved_at',
        'times_seen',
        'last_message',
        'last_context',
    ];

    protected $casts = [
        'context'      => 'array',
        'source'       => 'array',
        'occurred_at'  => 'datetime',
        // episode fields
        'first_seen_at'=> 'datetime',
        'last_seen_at' => 'datetime',
        'resolved_at'  => 'datetime',
        'last_context' => 'array',
        'times_seen'   => 'integer',
    ];

    /* ===================== Relations ===================== */

    public function entity(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'entity_type', 'entity_id');
    }

    /* ===================== Scopes ===================== */

    public function scopeForEntity(Builder $q, string $type, string|int $id): Builder
    {
        return $q->where('entity_type', $type)->where('entity_id', (string) $id);
    }

    public function scopeForModule(Builder $q, string $module): Builder
    {
        return $q->where('module', $module);
    }

    public function scopeForName(Builder $q, string $name): Builder
    {
        return $q->where('name', $name);
    }

    public function scopeForModuleAndName(Builder $q, string $module, string $name): Builder
    {
        return $q->where('module', $module)->where('name', $name);
    }

    public function scopeWithCode(Builder $q, string $code): Builder
    {
        return $q->where('code', $code);
    }

    public function scopeLevel(Builder $q, string $level): Builder
    {
        return $q->where('level', $level);
    }

    public function scopeProblems(Builder $q): Builder
    {
        return $q->whereIn('level', [
            \App\Services\Logging\SystemLogService::LEVEL_WARNING,
            \App\Services\Logging\SystemLogService::LEVEL_ERROR,
            \App\Services\Logging\SystemLogService::LEVEL_CRITICAL,
        ]);
    }

    public function scopeBetween(Builder $q, $from, $to): Builder
    {
        return $q->whereBetween('occurred_at', [$from, $to]);
    }

    public function scopeRecent(Builder $q): Builder
    {
        return $q->orderByDesc('occurred_at')->orderByDesc('id');
    }

    /* ===================== Attributes ===================== */

    protected function shortMessage(): Attribute
    {
        return Attribute::get(fn () => Str::limit((string) $this->message, 140));
    }

    /* ===================== Hooks ===================== */

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->occurred_at)) {
                $model->occurred_at = now();
            }
            if (empty($model->dedup_hash)) {
                $model->dedup_hash = hash('xxh128', json_encode([
                    $model->module,
                    $model->name,
                    $model->level,
                    $model->entity_type,
                    (string) $model->entity_id,
                    $model->code,
                    $model->message,
                    $model->context,
                ], JSON_UNESCAPED_UNICODE));
            }
        });
    }
}
