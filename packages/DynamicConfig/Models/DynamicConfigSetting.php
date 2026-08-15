<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Модель DynamicConfigSetting
 *
 * Представляет запись в таблице dynamic_config_settings.
 *
 * @property int                      $id
 * @property string                   $scope_type
 * @property int|null                 $scope_id
 * @property string                   $key
 * @property array<mixed>|null        $value
 * @property \DateTimeInterface|null  $expires_at
 * @property \DateTimeInterface|null  $deleted_at
 * @property \DateTimeInterface|null  $created_at
 * @property \DateTimeInterface|null  $updated_at
 */
class DynamicConfigSetting extends Model
{
    use SoftDeletes;

    protected $table = 'dynamic_config_settings';

    /**
     * Разрешённые для массового заполнения поля.
     *
     * @var string[]
     */
    protected $fillable = [
        'scope_type',
        'scope_id',
        'key',
        'value',
        'expires_at',
    ];

    /**
     * Приведение типов.
     *
     *  value      — массив (JSON → array),
     *  expires_at — datetime,
     *  deleted_at — datetime.
     */
    protected $casts = [
        'value'      => 'array',
        'expires_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
