<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Модель DynamicConfigLock
 *
 * Представляет запись в таблице dynamic_config_locks.
 *
 * Используется для временной или постоянной блокировки ключей настроек.
 *
 * @property int                      $id
 * @property string                   $scope_type
 * @property int|null                 $scope_id
 * @property string                   $key
 * @property \DateTimeInterface|null  $locked_until
 * @property bool                     $locked_permanent
 * @property string|null              $reason
 * @property int|null                 $created_by
 * @property \DateTimeInterface|null  $created_at
 */
class DynamicConfigLock extends Model
{
    protected $table = 'dynamic_config_locks';

    /**
     * У данной таблицы нет столбцов updated_at / created_at в стандартном виде,
     * поэтому timestamps отключены.
     */
    public $timestamps = false;

    /**
     * Разрешённые для массового заполнения поля.
     *
     * @var string[]
     */
    protected $fillable = [
        'scope_type',
        'scope_id',
        'key',
        'locked_until',
        'locked_permanent',
        'reason',
        'created_by',
        'created_at',
    ];

    /**
     * Приведение типов.
     */
    protected $casts = [
        'locked_until'     => 'datetime',
        'locked_permanent' => 'bool',
        'created_at'       => 'datetime',
    ];
}
