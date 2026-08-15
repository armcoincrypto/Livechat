<?php
declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderPaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class TaskMeta
 *
 * Метаданные заявки ("снимок" состояния на момент создания/обновления).
 *
 * ◼️ Единый источник правды по выбранным комиссиям — `selected_fees`.
 * Хранится массив элементов вида:
 *   {
 *     id:int,
 *     scope:'common'|'individual',
 *     details?:{
 *       name?:string,
 *       description?:string,
 *       fee?:string,          // например: "1", "-0.5%", "1%"
 *       fee_type?:'dynamic'|'profit',
 *       sorting?:int,
 *       snapshot_at?:string,  // ISO8601
 *       version?:int
 *     }
 *   }
 * Эти данные используются для UI и расчёта БЕЗ дополнительных SQL-запросов (снимок фиксируется при создании).
 *
 * ◼️ Инварианты/ограничения:
 *  - уникальность по паре {id|scope} внутри массива;
 *  - `scope` по умолчанию `'common'`;
 *  - `fee_type` по умолчанию `'dynamic'`;
 *  - допустимо отсутствие `details` у старых записей (fallback-дозаполнение выполняется на этапе enrichment, не здесь).
 *
 * @property int                                     $id
 * @property int                                     $task_id
 * @property \App\Enums\OrderPaymentStatus|null    $payment_status
 * @property int|string|null                         $telegram_id
 * @property array|null                              $telegram_data
 * @property array|null                              $checkbox_agreements
 * @property bool|null                               $card_verification_required
 * @property int|null                                $card_verification_type
 * @property string|null                             $merchant_network_code
 * @property string|null                             $user_agent
 * @property string|null                             $user_flag
 * @property array<int, array{id:int,scope:string,details?:array<string,mixed>}> $selected_fees
 *
 * @property-read \App\Models\Task                 $task
 *
 * @property int|null    $selected_fee_id              @deprecated Используйте `selected_fees`
 * @property int|null    $direction_selected_fee_id    @deprecated Используйте `selected_fees`
 * @property string|null $selected_fee_type            @deprecated Используйте `selected_fees`
 */
class TaskMeta extends Model
{
    protected $table = 'tasks_meta';

    /**
     * Разрешённые поля для массового присвоения (mass assignment).
     * Внимание: поля legacy (`selected_fee_id`, `direction_selected_fee_id`, `selected_fee_type`) оставлены только
     * для обратной совместимости со старыми записями. Новая логика их не заполняет и не использует.
     */
    protected $fillable = [
        'task_id',
        'payment_status',
        'telegram_id',
        'telegram_data',

        'selected_fee_id', // устарело
        'direction_selected_fee_id', // устарело
        'selected_fee_type',
        'checkbox_agreements',
        'card_verification_required',
        'card_verification_type',
        'identity_verification_required',
        'identity_verification_type',
        'merchant_network_code',
        'user_agent',
        'user_flag',
        'device_type',
        'selected_fees',
        'geo_data',
        'freeze_scam',
        'chat_handoff_to_human',
    ];

    /**
     * Касты атрибутов модели.
     * `selected_fees` приводится к массиву (JSON-снимок, подготовленный на этапе создания заявки).
     * Бизнес-логики и SQL в кастах/аксессорах нет — расчёт и форматирование делаются во внешних сервисах/презентерах.
     */
    protected $casts = [
        'geo_data' => 'array',
        'payment_status' => OrderPaymentStatus::class,
        'telegram_data' => 'array',
        'checkbox_agreements' => 'array',
        'identity_verification_required' => 'boolean',
        'selected_fees' => 'array',
        'freeze_scam' => 'array',
        'chat_handoff_to_human' => 'boolean',
    ];

    /** Родительская заявка. */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
