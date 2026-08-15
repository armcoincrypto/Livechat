<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $visible_fon
 * @property int $background_svg
 * @property int|null $start_sharing Стиль кнопки "Начать обмен"
 * @property string|null $fon
 * @property string|null $background_position_1
 * @property string|null $background_position_2
 * @property string|null $background_repeat
 * @property string|null $background_size
 * @property int $full_background
 * @property string|null $favicon
 * @property string|null $logotype
 * @property string|null $textlogotype
 * @property int $select_logotype
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|SettingTheme newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|SettingTheme newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|SettingTheme query()
 * @method static \Illuminate\Database\Eloquent\Builder|SettingTheme whereBackgroundPosition1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SettingTheme whereBackgroundPosition2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SettingTheme whereBackgroundRepeat($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SettingTheme whereBackgroundSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SettingTheme whereBackgroundSvg($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SettingTheme whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SettingTheme whereFavicon($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SettingTheme whereFon($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SettingTheme whereFullBackground($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SettingTheme whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SettingTheme whereLogotype($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SettingTheme whereSelectLogotype($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SettingTheme whereStartSharing($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SettingTheme whereTextlogotype($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SettingTheme whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SettingTheme whereVisibleFon($value)
 * @mixin \Eloquent
 */
class SettingTheme extends Model
{
    protected $table = 'settings_theme';
}
