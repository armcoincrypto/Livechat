<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Translatable\HasTranslations;

/**
 * Модель метки для валюты.
 *
 * Поля:
 *  - title: string|array — локализуемое поле (spatie/laravel-translatable)
 *  - text_color: string|null — HEX-цвет текста (например, "#ffffff")
 *  - bg_color: string|null  — HEX-цвет фона (например, "#3366ff")
 *  - image: string|null     — путь/имя файла иконки метки
 *
 * Отношения:
 *  - currencies(): BelongsToMany — валюты, к которым привязана метка
 *    Pivot-поля: side ('give'|'receive'), priority (int), is_active (0|1)
 *
 * @property int $id
 * @property string|array $title
 * @property string|null $text_color
 * @property string|null $bg_color
 * @property string|null $image
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int,\App\Models\Currency> $currencies
 */
class CurrencyLabel extends Model
{
    use HasTranslations;

    protected $table = 'currencies_labels';

    protected $fillable = [
        'title',
        'text_color',
        'bg_color',
        'image',
    ];

    protected $translatable = [
        'title',
    ];

    /**
     * Связь «многие-ко-многим» с валютами через таблицу `currency_label_currency`.
     * Pivot-поля:
     *  - side: 'give' | 'receive' — сторона применения метки
     *  - priority: целочисленный приоритет сортировки (меньше — выше)
     *  - is_active: 1/0 — флаг активности связи
     *
     * @return BelongsToMany<\App\Models\Currency>
     */
    public function currencies(): BelongsToMany
    {
        return $this->belongsToMany(Currency::class, 'currency_label_currency', 'label_id', 'currency_id')
            ->withPivot(['side','priority','is_active'])
            ->withTimestamps();
    }
}
