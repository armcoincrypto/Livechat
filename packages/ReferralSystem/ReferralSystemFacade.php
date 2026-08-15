<?php

declare(strict_types=1);

namespace iEXPackages\ReferralSystem;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Facade;
use iEXPackages\ReferralSystem\DTO\CreditResult;
use iEXPackages\ReferralSystem\DTO\ReferralPreview;
use iEXPackages\ReferralSystem\ReferralEngine;

/**
 * ReferralSystemFacade
 *
 * Единственная точка входа для работы с реферальной системой.
 *
 * @method static void register(User $user, ?string $ref = null)
 * @method static CreditResult creditForTask(Task $task, ?string $bonusCurrency = null)
 * @method static ReferralPreview previewForTask(Task $task, ?string $bonusCurrency = null)
 * @method static bool reverseForTask(Task $task, string $reason = 'clawback')
 * @method static CreditResult restartForTask(Task $task, string $reason = 'recalculate')
 * @method static int settleDueHolds(int $limit = 500)
 *
 * @see ReferralEngine
 */
final class ReferralSystemFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'referral_system';
    }
}
