<?php
/**
 * Created by PhpStorm.
 * User: steei
 * Date: 01.10.2017
 * Time: 17:51
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string $title_about
 * @property string $text_about
 * @property int $about_visible
 * @property string $text_contact
 * @property string $title_faq
 * @property string $text_faq
 * @property string $text_exchange_rules
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|PagesStatic newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PagesStatic newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PagesStatic query()
 * @method static \Illuminate\Database\Eloquent\Builder|PagesStatic whereAboutVisible($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PagesStatic whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PagesStatic whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PagesStatic whereTextAbout($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PagesStatic whereTextContact($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PagesStatic whereTextExchangeRules($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PagesStatic whereTextFaq($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PagesStatic whereTitleAbout($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PagesStatic whereTitleFaq($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PagesStatic whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class PagesStatic extends Model
{
    protected $table = 'pages_static';

    protected $fillable = [
        'title_about', 'text_about', 'about_visible', 'text_contact', 'title_faq', 'text_faq', 'text_exchange_rules',
    ];
}
