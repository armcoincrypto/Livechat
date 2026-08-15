<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int $id
 * @property int $id_task
 * @property float $profit_percent
 * @property float $profit_currency
 * @property float $profit_usd
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|TaskProfit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskProfit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskProfit query()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskProfit whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskProfit whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskProfit whereIdTask($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskProfit whereProfitCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskProfit whereProfitPercent($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskProfit whereProfitRub($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskProfit whereProfitUsd($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskProfit whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class TaskProfit extends Model
{
    protected $table = 'tasks_profits';

    protected $fillable = [
        'id_task',
        'profit_percent',
        'profit_currency',
        'profit_usd'
    ];
}
