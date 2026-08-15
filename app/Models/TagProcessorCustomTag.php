<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $key
 * @property string|null $label
 * @property string|null $description
 * @property string|null $group
 * @property string $type
 * @property string $scope
 * @property string|null $value
 * @property array|null $meta
 * @property bool $is_active
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class TagProcessorCustomTag extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'tag_processor_custom_tags';

    protected $fillable = [
        'key',
        'label',
        'description',
        'group',
        'type',
        'scope',
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
     * Фильтр по группе (marketing, email, footer и т.п.).
     */
    public function scopeGroup(Builder $query, ?string $group): Builder
    {
        if ($group === null) {
            return $query;
        }

        return $query->where('group', $group);
    }

    /**
     * Фильтр по scope (email, site, any и т.п.).
     */
    public function scopeScope(Builder $query, ?string $scope): Builder
    {
        if ($scope === null) {
            return $query;
        }

        return $query->where('scope', $scope);
    }

    /*
    |--------------------------------------------------------------------------
    | Relations (опционально, если есть модель User)
    |--------------------------------------------------------------------------
    */

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
