<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $id_task
 * @property string|null $provider_name
 * @property string|null $event_value
 * @property int $event_type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|LogErrorMerchant newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|LogErrorMerchant newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|LogErrorMerchant query()
 * @method static \Illuminate\Database\Eloquent\Builder|LogErrorMerchant whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LogErrorMerchant whereEventType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LogErrorMerchant whereEventValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LogErrorMerchant whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LogErrorMerchant whereIdTask($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LogErrorMerchant whereProviderName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LogErrorMerchant whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LogErrorMerchant extends Model
{
    protected $table = 'log_error_merchants';

    protected $fillable = [
        'id_task',
        'provider_name',
        'event_value',
        'event_type',
    ];
}
