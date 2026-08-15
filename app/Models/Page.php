<?php
namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;


class Page extends Model
{
    use SoftDeletes, HasTranslations;

    /**
     * Таблица базы данных, используемая моделью.
     *
     * @var string
     */
    protected $table = 'pages';

    /**
     * Первичный ключ.
     *
     * @var string
     **/
    protected $primaryKey = 'page_id';

    protected $fillable = [
        'page_title',
        'page_content',
        'page_slug',
        'page_headline',
        'user_id',

        'group_id',
        'sort_order',
        'is_active',
    ];

    /**
     * Это для функциональности SoftDeleting.
     *
     * @var bool
     */
    protected $dates = ['deleted_at'];

    /**
     * Для мультиязычности
    */
    public $translatable = ['page_title', 'page_content', 'page_headline'];


    public function user()
    {
        return $this->hasOne(User::class, 'id','user_id');
    }

    /**
     * Группа страницы (nullable — одиночная страница)
     */
    public function group()
    {
        return $this->belongsTo(PageGroup::class, 'group_id', 'id');
    }

    /**
     * Только активные страницы
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Одиночные страницы (без группы)
     */
    public function scopeSingle($query)
    {
        return $query->whereNull('group_id');
    }

    /**
     * Страницы конкретной группы
     */
    public function scopeInGroup($query, int $groupId)
    {
        return $query->where('group_id', $groupId);
    }
}
