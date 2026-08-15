<?php

namespace App\Observers\User;

use App\Models\RewardProgram;
use App\Models\User;

/**
 * Class UserObserver.
 */
class UserObserver
{
    /**
     * Слушаем созданное пользователем событие.
     */
    public function created(User $user): void
    {
        // Привязываем бонусную систему после создания аккаунта
        $rewardProgram = RewardProgram::where('id')->first();
        // Привязываем бонусную систему
        if (isset($rewardProgram) and isset($rewardProgram->id)) {
            $user->update(['id_reward_program' => $rewardProgram->id]);
        }
    }

    /**
     * Слушаем обновленное пользователем событие.
     */
    public function updated(User $user): void
    {
        //
    }
}
