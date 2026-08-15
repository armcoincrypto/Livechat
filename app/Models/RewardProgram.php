<?php

namespace App\Models;

use App\Models\Filters\RewardProgramFilter;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 *
 *
 * @property int $id
 * @property string $name
 * @property string $percent
 * @property string|null $sign
 * @property int $is_reg
 * @property int $amount
 * @property array $title
 * @property array $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string $style_width
 * @property-read mixed $translations
 * @method static \Illuminate\Database\Eloquent\Builder|RewardProgram newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|RewardProgram newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|RewardProgram onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|RewardProgram query()
 * @method static \Illuminate\Database\Eloquent\Builder|RewardProgram whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RewardProgram whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RewardProgram whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RewardProgram whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RewardProgram whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RewardProgram whereIsReg($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RewardProgram whereJsonContainsLocale(string $column, string $locale, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|RewardProgram whereJsonContainsLocales(string $column, array $locales, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|RewardProgram whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder|RewardProgram whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder|RewardProgram whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RewardProgram wherePercent($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RewardProgram whereSign($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RewardProgram whereStyleWidth($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RewardProgram whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RewardProgram whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RewardProgram withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|RewardProgram withoutTrashed()
 * @mixin \Eloquent
 */
class RewardProgram extends Model
{
    use HasTranslations, SoftDeletes, Filterable;

    protected $table = 'reward_programs';

    /**
     * @var array
     */
    protected $fillable = [
        'name',
        'is_reg',
        'amount',
        'percent',
        'sign',
        'title',
        'description'
    ];

    protected $translatable = [
        'title',
        'description',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    /**
     * Фильтры
     *
     * @return string|null
     */
    public function modelFilter(): ?string
    {
        return $this->provideFilter(RewardProgramFilter::class);
    }
}
