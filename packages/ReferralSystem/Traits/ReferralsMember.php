<?php
/**
 * Новый файл без названия
 * Автор: Шамсудин
 * Дата и Время: 25.02.2018, 20:18
 */

namespace iEXPackages\ReferralSystem\Traits;

use iEXPackages\ReferralSystem\Models\ReferralLink;
use iEXPackages\ReferralSystem\Models\ReferralProgram;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Получение реферальных данных пользователя
 */
trait ReferralsMember
{
    /**
     * Получить все реферальные ссылки пользователя с программами
     *
     * @return HasMany
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(ReferralLink::class, 'user_id', 'id')->with('program');
    }

    /**
     * Получить одну реферальную ссылку пользователя
     *
     * @return HasOne
     */
    public function referralLink(): HasOne
    {
        return $this->hasOne(ReferralLink::class, 'user_id', 'id');
    }

    /**
     * Получить реферальную программу пользователя
     *
     * @return HasOne
     */
    public function referralProgram(): HasOne
    {
        return $this->hasOne(ReferralProgram::class, 'id', 'referral_program_id');
    }

    /**
     * Получение реферальной ссылки для конкретной программы
     *
     * @param ReferralProgram $program
     * @return ReferralLink|null
     */
    public function getReferralForProgram(ReferralProgram $program): ?ReferralLink
    {
        return ReferralLink::where('user_id', $this->id)
            ->where('referral_program_id', $program->id)
            ->first();
    }
}
