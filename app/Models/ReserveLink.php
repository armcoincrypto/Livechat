<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ReserveLink
 *
 * Прямая связь резерва (child -> parent).
 *
 * Таблица: reserve_links
 * - reserve_id        (PK, = child)
 * - parent_reserve_id (nullable, = parent)
 * - is_active
 * - note
 */
class ReserveLink extends Model
{
    protected $table = 'reserve_links';

    protected $primaryKey = 'reserve_id';

    public $incrementing = false;

    protected $keyType = 'int';

    /**
     * @var array<int,string>
     */
    protected $fillable = ['reserve_id', 'parent_reserve_id', 'is_active', 'note'];

    /**
     * @var array<string,string>
     */
    protected $casts = [
        'reserve_id' => 'int',
        'parent_reserve_id' => 'int',
        'is_active' => 'bool',
    ];

    /**
     * Резерв (child).
     *
     * @return BelongsTo<Reserve,ReserveLink>
     */
    public function reserve(): BelongsTo
    {
        return $this->belongsTo(Reserve::class, 'reserve_id', 'id');
    }

    /**
     * Родительский резерв (parent).
     *
     * @return BelongsTo<Reserve,ReserveLink>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Reserve::class, 'parent_reserve_id', 'id');
    }
}
