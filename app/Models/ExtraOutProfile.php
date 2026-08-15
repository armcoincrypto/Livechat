<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Translatable\HasTranslations;

/**
 * Class ExtraOutProfile
 *
 * Модель для работы с таблицей `extra_out_profiles`, которая хранит
 * настройки доп. реквизитов для операций «Получаю».
 *
 * Позволяет указывать:
 *  - Заголовок поля (`field_label`)
 *  - Описание (`description`)
 *  - Название кнопки (`button_name`)
 *  - Мин. сумму на один реквизит (`min_payout_amount`)
 *  - Мин. сумму «Получаю» для активации панели (`min_trigger_amount`)
 *  - Макс. кол-во реквизитов (`max_fields`)
 *  - Статус включения (`is_enabled`)
 *
 * Поддерживает мультиязычные поля с помощью пакета spatie/laravel-translatable.
 *
 * @property int         $id
 * @property string|null $field_label
 * @property bool        $is_enabled
 * @property string      $min_payout_amount
 * @property string      $min_trigger_amount
 * @property int         $max_fields
 * @property string|null $description
 * @property string|null $button_name
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Currency> $currencies
 */
class ExtraOutProfile extends Model
{

    use HasTranslations;

    /**
     * @var string Имя таблицы в базе данных
     */
    protected $table = 'extra_out_profiles';

    /**
     * @var array<int, string> Массово заполняемые атрибуты
     */
    protected $fillable = [
        'field_label',
        'is_enabled',
        'min_payout_amount',
        'min_trigger_amount',
        'max_fields',
        'description',
        'button_name',
    ];

    /**
     * @var array<string, string> Приведения типов атрибутов
     */
    protected $casts = [
        'is_enabled'         => 'boolean',
        'min_payout_amount'  => 'string',
        'min_trigger_amount' => 'string',
        'max_fields'         => 'integer',
    ];

    /**
     * @var array<int, string> Мультиязычные атрибуты
     */
    protected $translatable = [
        'field_label',
        'description',
        'button_name',
    ];

    /**
     * Валюты, к которым привязан данный профиль доп. реквизитов.
     *
     * Связь многие-ко-многим через таблицу `extra_out_profile_currencies`
     * с ключами `profile_id` и `currency_id`. Сохраняет метки времени.
     *
     * @return BelongsToMany<Currency>
     */
    public function currencies(): BelongsToMany
    {
        return $this->belongsToMany(
            Currency::class,
            'extra_out_profile_currencies',
            'profile_id',
            'currency_id'
        )->withTimestamps();
    }

    /**
     * Локальный скоуп: только включённые профили.
     *
     * @param Builder $q
     * @return Builder
     */
    public function scopeEnabled(Builder $q): Builder
    {
        return $q->where('is_enabled', true);
    }

    /**
     * Локальный скоуп: профиль для конкретной валюты.
     *
     * @param Builder $q
     * @param int     $currencyId
     * @return Builder
     */
    public function scopeForCurrency(Builder $q, int $currencyId): Builder
    {
        return $q->whereHas('currencies', fn ($c) => $c->where('currencies.id', $currencyId));
    }
}
