<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $id
 * @property int         $id_source
 * @property string      $source
 * @property string|null $name
 * @property string|null $old_value
 * @property string      $new_value
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class RatesHistoryLog extends Model
{
    protected $table = 'rates_history_logs';

    protected $fillable = [
        'id_source',
        'source',
        'name',
        'old_value',
        'new_value',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id_source' => 'int',
        'old_value' => 'string',
        'new_value' => 'string',
    ];
}
