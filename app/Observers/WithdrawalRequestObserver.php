<?php
/**
 * Created by PhpStorm.
 * User: steei
 * Date: 13.10.2019
 * Time: 21:02
 */

namespace App\Observers;

use App\Models\WithdrawalRequest;

/**
 * Class WithdrawalRequest.
 */
class WithdrawalRequestObserver
{
    /**
     * Слушаем созданное пользователем событие.
     */
    public function creating(WithdrawalRequest $withdrawalRequest): void
    {
        $withdrawalRequest->big_id = generate_ids();
    }
}
