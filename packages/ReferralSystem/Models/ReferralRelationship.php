<?php

namespace iEXPackages\ReferralSystem\Models;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReferralRelationship extends Model
{
    protected $table = 'referral_relationships';

    /**
     * Атрибуты, доступные для массового заполнения.
     *
     * @var array
     */
    protected $fillable = [
        'referral_link_id',
        'user_id',
    ];

    /**
     * Задачи (все) реферала.
     *
     * @return HasMany
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'id_user', 'user_id');
    }

    /**
     * Последний выполненный заказ реферала.
     *
     * @return HasMany
     */
    public function lastOrder(): HasMany
    {
        return $this->hasMany(Task::class, 'id_user', 'user_id')
            ->where('status', '=', 4)
            ->orderByDesc('id')
            ->limit(1);
    }

    /**
     * Все успешно выполненные задачи реферала.
     *
     * @return HasMany
     */
    public function completedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'id_user', 'user_id')
            ->where('status', 4);
    }

    /**
     * Ссылка на реферальную программу.
     *
     * @return BelongsTo
     */
    public function referralLink(): BelongsTo
    {
        return $this->belongsTo(ReferralLink::class, 'referral_link_id', 'id');
    }

    /**
     * Пользователь, привязанный к этому реферальному отношению.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }


    /**
     * @deprecated
    */
    public function referral_link(): BelongsTo
    {
        return $this->belongsTo(ReferralLink::class, 'referral_link_id', 'id');
    }
}
