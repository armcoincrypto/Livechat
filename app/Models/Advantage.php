<?php
/**
 * Created by PhpStorm.
 * User: steei
 * Date: 24.03.2019
 * Time: 11:22
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 *
 *
 * @property int $id
 * @property int $id_user
 * @property array|null $title
 * @property array|null $content
 * @property string|null $icon
 * @property string|null $link
 * @property bool $is_target
 * @property int $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $sorting
 * @property-read mixed $translations
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder|Advantage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Advantage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Advantage query()
 * @method static \Illuminate\Database\Eloquent\Builder|Advantage whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Advantage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Advantage whereIcon($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Advantage whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Advantage whereIdUser($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Advantage whereIsTarget($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Advantage whereJsonContainsLocale(string $column, string $locale, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|Advantage whereJsonContainsLocales(string $column, array $locales, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|Advantage whereLink($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Advantage whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder|Advantage whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder|Advantage whereSorting($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Advantage whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Advantage whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Advantage whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Advantage extends Model
{
    use HasTranslations;

    protected $table = 'advantage';

    public $translatable = ['title', 'content'];

    protected $fillable = [
        'id_user',
        'title',
        'content',
        'icon',
        'link',
        'is_target',
        'status',
        'sorting',
        'colspan',
        'rowspan'
    ];

    /**
     * Атрибуты, которые должны быть приведены к нативным типам.
     *
     * @var array
     */
    protected $casts = [
        'is_target' => 'boolean',
        'status' => 'integer',
    ];

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'id_user');
    }
}
