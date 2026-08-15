<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Filters\ReservesFilter;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Reserve
 *
 * ORM-модель резерва валюты.
 *
 * Важно:
 * - Модель хранит только данные и связи.
 * - Любая логика цепочек (корень/потомки/эффективная сумма) находится в сервисах
 *   ReserveLinkManager / ReserveLinkResolver.
 */
class Reserve extends Model
{
    use Filterable;

    protected $table = 'reserves';

    /**
     * @var array<int,string>
     */
    protected $fillable = [
        'id_currency',
        'id_code_currency',
        'id_user',
        'summa',
        'black_amount',
        'status',
        'is_non_standard',
        'id_group',
        'sorting',
        'is_star',
        'is_fixed_reserve',
        'id_file_reserve',
        'id_server_reserve',
    ];

    /**
     * @var array<string,string>
     */
    protected $casts = [
        'id' => 'int',
        'id_currency' => 'int',
        'id_code_currency' => 'int',
        'id_user' => 'int',
        'status' => 'int',
        'is_non_standard' => 'int',
        'id_group' => 'int',
        'sorting' => 'int',
        'is_star' => 'int',
        'is_fixed_reserve' => 'int',
        'id_file_reserve' => 'int',
        'summa' => 'decimal:18',
        'black_amount' => 'decimal:18',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function modelFilter(): ?string
    {
        return $this->provideFilter(ReservesFilter::class);
    }

    /**
     * Валюта резерва.
     *
     * @return BelongsTo<Currency,Reserve>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'id_currency', 'id');
    }

    /**
     * Пользователь, который последним менял резерв (если id_user используется как last editor).
     *
     * @return BelongsTo<User,Reserve>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id');
    }

    /**
     * Ручные события по резерву.
     *
     * @return HasMany<ReserveManualEvent>
     */
    public function reserve_manual_event(): HasMany
    {
        return $this->hasMany(ReserveManualEvent::class, 'id_reserve', 'id');
    }

    /**
     * Ledger проводки резерва.
     *
     * @return HasMany<ReserveLedger>
     */
    public function ledgers(): HasMany
    {
        return $this->hasMany(ReserveLedger::class, 'reserve_id', 'id');
    }

    /**
     * Дневные агрегаты ledger.
     *
     * @return HasMany<ReserveLedgerDaily>
     */
    public function ledger_daily(): HasMany
    {
        return $this->hasMany(ReserveLedgerDaily::class, 'reserve_id', 'id');
    }

    /**
     * Прямая ссылка в reserve_links для этого резерва.
     *
     * @return HasOne<ReserveLink>
     */
    public function link(): HasOne
    {
        return $this->hasOne(ReserveLink::class, 'reserve_id', 'id');
    }

    /**
     * Ссылки дочерних резервов, которые выбрали текущий резерв как parent.
     *
     * @return HasMany<ReserveLink>
     */
    public function child_links(): HasMany
    {
        return $this->hasMany(ReserveLink::class, 'parent_reserve_id', 'id');
    }
}
