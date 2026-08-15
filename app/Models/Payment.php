<?php

namespace App\Models;

use App\Models\Filters\PaymentFilter;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 *
 *
 * @property int $id
 * @property array $name
 * @property string|null $logo
 * @property int $enable_svg
 * @property string|null $svg_name
 * @property string|null $svg_class
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property int $is_delete
 * @property string|null $blockchain_url
 * @property int $is_import
 * @property string|null $logo_svg
 * @property int $is_local_image
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Currency> $currencies
 * @property-read int|null $currencies_count
 * @property-read \App\Models\PaymentExplorer|null $explorer
 * @property-read mixed $translations
 * @method static \Illuminate\Database\Eloquent\Builder|Payment active()
 * @method static \Illuminate\Database\Eloquent\Builder|Payment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Payment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Payment query()
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereBlockchainUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereEnableSvg($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereIsDelete($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereIsImport($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereIsLocalImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereJsonContainsLocale(string $column, string $locale, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereJsonContainsLocales(string $column, array $locales, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereLogo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereLogoSvg($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereSvgClass($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereSvgName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Payment extends Model
{
    use HasTranslations,
        Filterable,
        SoftDeletes;

    protected $table = 'payments';

    protected $fillable = [
        'name',
        'logo',
        'logo_svg',
        'enable_svg',
        'svg_name',
        'svg_class',
        'is_delete',
        'blockchain_url',
        'is_import',
        'is_local_image',
    ];

    public $translatable = ['name'];


    /**
     * Фильтры
     */
    public function modelFilter()
    {
        return $this->provideFilter(PaymentFilter::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_delete', '!=', 1);
    }

    public function explorer()
    {
        return $this->hasOne(PaymentExplorer::class, 'id_payment', 'id');
    }

    public function currencies()
    {
        return $this->hasMany(Currency::class, 'id_payment', 'id');
    }
}
