<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class TaskExtraOut
 *
 * Модель для работы с таблицей `task_extra_outs`, которая хранит
 * дополнительные реквизиты (label/amount) для конкретной задачи.
 *
 * @property int         $id
 * @property int         $id_task   ID связанной задачи
 * @property string|null $label     Название/метка реквизита
 * @property string      $amount    Сумма (строкой для точности)
 * @property int         $position  Позиция реквизита в списке
 *
 * @property-read Task   $task      Связанная задача
 */
class TaskExtraOut extends Model
{
    /**
     * @var string Имя таблицы в базе данных
     */
    protected $table = 'task_extra_outs';

    /**
     * @var array<int, string> Массово заполняемые атрибуты
     */
    protected $fillable = [
        'id_task',
        'label',
        'amount',
        'position',
    ];

    /**
     * Задача, к которой относится этот дополнительный реквизит.
     *
     * @return BelongsTo<Task, TaskExtraOut>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'id_task');
    }
}
