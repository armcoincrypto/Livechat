<?php
/**
 * Created by PhpStorm.
 * User: steei
 * Date: 05.10.2019
 * Time: 7:57
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string|null $name
 * @property float $amount
 * @property int $sorting
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $id_currency
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CurrencyCommand> $commands
 * @property-read int|null $commands_count
 * @property-read \App\Models\Currency|null $currency
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyCommand newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyCommand newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyCommand query()
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyCommand whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyCommand whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyCommand whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyCommand whereIdCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyCommand whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyCommand whereSorting($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyCommand whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CurrencyCommand extends Model
{
    protected $table = 'currencies_commands';

    protected $fillable = [
        'name',
        'amount',
        'id_currency',
        'sorting',
    ];

    public function currency()
    {
        return $this->hasOne(Currency::class, 'id', 'id_currency');
    }

    public function commands()
    {
        return $this->hasMany(CurrencyCommand::class, 'id', 'id');
    }
}
