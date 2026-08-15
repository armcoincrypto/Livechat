<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Модель правила по банкам для конкретной валюты.
 *
 * Таблица: currency_bin_bank_rules
 *
 * Каждая запись описывает:
 *  - к какой валюте относится правило;
 *  - для какого направления (in/out);
 *  - что это за правило (allow/block);
 *  - для какого банка оно применяется.
 */
class CurrencyBinBankRule extends Model
{
    /**
     * Имя таблицы.
     *
     * @var string
     */
    protected $table = 'currency_bin_bank_rules';

    /**
     * Массово заполняемые поля.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'currency_id',
        'direction',
        'mode',
        'bank_name',
    ];

    /**
     * Связь с моделью Currency.
     *
     * @return BelongsTo
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
