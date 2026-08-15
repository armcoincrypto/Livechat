<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 *
 *
 * @property int $id
 * @property array $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $is_not_delete
 * @property-read mixed $translations
 * @method static \Illuminate\Database\Eloquent\Builder|PendingOrderStatus newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PendingOrderStatus newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PendingOrderStatus query()
 * @method static \Illuminate\Database\Eloquent\Builder|PendingOrderStatus whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PendingOrderStatus whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PendingOrderStatus whereIsNotDelete($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PendingOrderStatus whereJsonContainsLocale(string $column, string $locale, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|PendingOrderStatus whereJsonContainsLocales(string $column, array $locales, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|PendingOrderStatus whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder|PendingOrderStatus whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder|PendingOrderStatus whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PendingOrderStatus whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class PendingOrderStatus extends Model
{
    use HasTranslations;

    protected $table = 'pending_order_status';

    protected $fillable = [
        'name',
        'is_not_delete',
    ];

    protected $translatable = [
        'name',
    ];
}
