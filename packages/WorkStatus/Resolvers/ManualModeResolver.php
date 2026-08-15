<?php

declare(strict_types=1);

namespace iEXPackages\WorkStatus\Resolvers;

use App\Settings\WorkStatusConfig;
use Carbon\CarbonInterface;
use iEXPackages\WorkStatus\Contracts\WorkStatusResolverInterface;
use iEXPackages\WorkStatus\DTO\WorkStatusResult;
use iEXPackages\WorkStatus\Enums\BaseMode;
use iEXPackages\WorkStatus\Enums\ManualState;

final class ManualModeResolver implements WorkStatusResolverInterface
{
    public function __construct(private readonly WorkStatusConfig $config) {}

    public function resolve(CarbonInterface $now): ?WorkStatusResult
    {
        if ($this->config->baseMode() !== BaseMode::Manual) {
            return null;
        }

        $state = $this->config->manualState();

        return new WorkStatusResult(
            isOnline: $state === ManualState::Online,
            baseMode: BaseMode::Manual,
            source: WorkStatusResult::SOURCE_MANUAL,
            reason: $this->config->overrideReason() ?? ($state === ManualState::Online ? 'manual_online' : 'manual_offline'),
            allowOverride: $this->config->allowOverride(),
            overrideMode: $this->config->overrideMode(),
            overrideUntil: $this->config->overrideUntil(),
            scheduleRuleId: null,
            nextChangeAt: null,
            manualState: $state,
        );
    }
}
