<?php

declare(strict_types=1);

namespace iEXPackages\ReferralSystem\DTO;

use App\Models\Task;
use App\Models\User;
use App\Models\DirectionExchange;
use iEXPackages\ReferralSystem\Models\ReferralLink;

class ReferralContext
{
    public function __construct(
        public ?Task $task,                   // если начисление по задаче
        public ?string $eventKey,             // если начисление по событию
        public User $client,
        public User $partner,
        public DirectionExchange $direction,
        public ?ReferralLink $referralLink,
        public string $bonusCurrency,         // например USD
    ) {}
}
