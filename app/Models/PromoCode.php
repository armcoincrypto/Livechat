<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromoCode extends Model
{
    protected $table = 'promo_codes';

    public const SCOPE_ALL = 'all';
    public const SCOPE_INCLUDE = 'include';

    public const MODE_INCLUDE = 'include';
    public const MODE_EXCLUDE = 'exclude';

    public const DISCOUNT_PERCENT = 'percent';
    public const DISCOUNT_FIXED = 'fixed';

    protected $fillable = [
        'name',
        'id_manager',
        'count_uses',
        'used',
        'discount_type',
        'discount_value',
        'code',
        'started_at',
        'expired_at',
        'status',
        'scope_mode',
    ];

    protected $casts = [
        'count_uses' => 'integer',
        'used' => 'integer',
        'status' => 'integer',
        'discount_value' => 'decimal:2',
        'started_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_manager', 'id');
    }

    public function directionsIncluded(): BelongsToMany
    {
        return $this->belongsToMany(DirectionExchange::class, 'promo_code_directions', 'promo_code_id', 'direction_exchange_id')
            ->withPivot(['mode'])
            ->wherePivot('mode', self::MODE_INCLUDE);
    }

    public function directionsExcluded(): BelongsToMany
    {
        return $this->belongsToMany(DirectionExchange::class, 'promo_code_directions', 'promo_code_id', 'direction_exchange_id')
            ->withPivot(['mode'])
            ->wherePivot('mode', self::MODE_EXCLUDE);
    }

    public function directionsAll(): BelongsToMany
    {
        return $this->belongsToMany(
            DirectionExchange::class,
            'promo_code_directions',
            'promo_code_id',
            'direction_exchange_id'
        )->withPivot(['mode']);
    }
}
