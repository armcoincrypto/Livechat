<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $entity_type
 * @property int $entity_id
 * @property string $key
 * @property string|null $group
 * @property string $type
 * @property string|null $value
 * @property array|null $meta
 * @property bool $is_active
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class TagProcessorEntityCustomTag extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'tag_processor_entity_custom_tags';

    protected $fillable = [
        'entity_type',
        'entity_id',
        'key',
        'label',
        'description',
        'group',
        'type',
        'value',
        'meta',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'meta'      => 'array',
        'is_active' => 'bool',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    /**
     * Полиморфная связь с любой сущностью (Order, User, DirectionExchange и т.п.).
     */
    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Опционально: авторы.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Только активные теги.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Фильтр по сущности.
     */
    public function scopeForEntity(Builder $query, Model $model): Builder
    {
        return $query
            ->where('entity_type', $model::class)
            ->where('entity_id', $model->getKey());
    }

    /**
     * Фильтр по группе.
     */
    public function scopeGroup(Builder $query, ?string $group): Builder
    {
        if ($group === null) {
            return $query;
        }

        return $query->where('group', $group);
    }
}
