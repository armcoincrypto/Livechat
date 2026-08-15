<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 *
 *
 * @property int $id
 * @property int $id_currency
 * @property string|null $description
 * @property string|null $css_class
 * @property int $sorting
 * @property int $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Currency|null $currency
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyNotification newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyNotification newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyNotification query()
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyNotification whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyNotification whereCssClass($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyNotification whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyNotification whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyNotification whereIdCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyNotification whereSorting($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyNotification whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyNotification whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CurrencyNotification extends Model
{
    use HasTranslations;

    protected $table = 'currencies_notification';

    protected $fillable = [
        'id_currency',
        'description',
        'css_class',
        'sorting',
        'status',
    ];

    protected $translatable = ['description'];

    public function currency()
    {
        return $this->hasOne(Currency::class, 'id', 'id_currency');
    }
}
