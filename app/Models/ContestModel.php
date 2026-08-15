<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\Translatable\HasTranslations;

/**
 *
 *
 * @property int $id
 * @property int $id_manager
 * @property int $status
 * @property string|null $name
 * @property \Illuminate\Support\Carbon|null $duration
 * @property int $max_limit_user
 * @property int $is_manual_bank
 * @property string $bank_base
 * @property string $bank
 * @property int $id_code_currency
 * @property string|null $code_name
 * @property string|null $code_sign
 * @property int $code_position
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property float $percent
 * @property array|null $title
 * @property array|null $subtitle
 * @property array|null $button_name
 * @property string|null $subtitle_color
 * @property string|null $title_color
 * @property string|null $icon_url_home
 * @property string|null $icon_url_account
 * @property array|null $info_title
 * @property array|null $info_text
 * @property-read \App\Models\CodeCurrency|null $code_currency
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ContestsUser> $contests_has_user
 * @property-read int|null $contests_has_user_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ContestsUser> $contests_user
 * @property-read int|null $contests_user_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ContestsUser> $contests_waiting_user
 * @property-read int|null $contests_waiting_user_count
 * @property-read mixed $translations
 * @method static \Illuminate\Database\Eloquent\Builder|ContestModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ContestModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ContestModel query()
 * @method static \Illuminate\Database\Eloquent\Builder|ContestModel whereBank($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestModel whereBankBase($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestModel whereButtonName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestModel whereCodeName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestModel whereCodePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestModel whereCodeSign($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestModel whereDuration($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestModel whereIconUrlAccount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestModel whereIconUrlHome($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestModel whereIdCodeCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestModel whereIdManager($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestModel whereInfoText($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestModel whereInfoTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestModel whereIsManualBank($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestModel whereJsonContainsLocale(string $column, string $locale, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestModel whereJsonContainsLocales(string $column, array $locales, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestModel whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestModel whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestModel whereMaxLimitUser($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestModel whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestModel wherePercent($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestModel whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestModel whereSubtitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestModel whereSubtitleColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestModel whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestModel whereTitleColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestModel whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class ContestModel extends Model
{
    use HasTranslations;

    protected $table = 'contests';

    protected $translatable = [
        'title',
        'subtitle',
        'button_name',
        'info_title',
        'info_text',
    ];

    protected $fillable = [
        'id_user',
        'id_manager',
        'status',
        'name',
        'duration',
        'started_at',
        'max_limit_user',
        'is_manual_bank',
        'bank',
        'bank_base',
        'percent',

        'id_code_currency',
        'code_name',
        'code_sign',
        'code_position',

        'title',
        'subtitle',
        'button_name',

        'title_color',
        'subtitle_color',
        'icon_url_home',
        'icon_url_account',

        'info_title',
        'info_text',
    ];

    /**
     * Информационные поля
     */
    public function contests_has_user(): MorphToMany
    {
        return $this->morphToMany(
            ContestsUser::class,
            'model',
            'contests_has_contests_users',
            'model_id',
            'contests_user_id'
        );
    }

    public function contests_user(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ContestsUser::class, 'id_contest', 'id');
    }

    public function contests_waiting_user(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ContestsUser::class, 'id_contest', 'id')->where('status', '=', 0);
    }

    public function code_currency(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CodeCurrency::class, 'id', 'id_code_currency');
    }
}
