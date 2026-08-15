<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Class GroupCommission
 *
 * Модель для работы с групповыми комиссиями.
 *
 * @property int $id Идентификатор групповой комиссии
 * @property string $name Название групповой комиссии
 * @property string|null $receiving Значение комиссии (формула или фиксированное значение)
 * @property \Illuminate\Support\Carbon $created_at Дата создания
 * @property \Illuminate\Support\Carbon $updated_at Дата последнего изменения
 *
 * @property-read DirectionExchange[]|\Illuminate\Database\Eloquent\Collection $directions Связанные направления обмена
 */
class GroupCommission extends Model
{
    /**
     * Название таблицы в БД.
     *
     * @var string
     */
    protected $table = 'group_commission';

    /**
     * Атрибуты, доступные для массового заполнения.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'receiving',
    ];

    /**
     * Направления обмена, связанные с этой групповой комиссией (связь многие ко многим).
     *
     * @return BelongsToMany
     */
    public function directions(): BelongsToMany
    {
        return $this->belongsToMany(
            DirectionExchange::class,
            'group_commission_direction_exchange',
            'group_commission_id',
            'direction_exchange_id'
        )->withTimestamps();
    }

    /**
     * Привязка направлений обмена к групповой комиссии.
     *
     * @param array $directionIds Массив ID направлений обмена
     * @return void
     */
    public function attachDirections(array $directionIds): void
    {
        $this->directions()->syncWithoutDetaching($directionIds);
    }

    /**
     * Отвязка направлений обмена от групповой комиссии.
     *
     * @param array $directionIds Массив ID направлений обмена
     * @return void
     */
    public function detachDirections(array $directionIds): void
    {
        $this->directions()->detach($directionIds);
    }

    /**
     * Полностью синхронизирует направления обмена с указанным массивом направлений.
     *
     * @param array $directionIds Массив ID направлений обмена
     * @return void
     */
    public function syncDirections(array $directionIds): void
    {
        $this->directions()->sync($directionIds);
    }
}
