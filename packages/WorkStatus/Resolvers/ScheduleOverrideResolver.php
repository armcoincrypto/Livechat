<?php

declare(strict_types=1);

namespace iEXPackages\WorkStatus\Resolvers;

use App\Settings\WorkStatusConfig;
use Carbon\CarbonInterface;
use iEXPackages\WorkStatus\Contracts\WorkStatusResolverInterface;
use iEXPackages\WorkStatus\DTO\WorkStatusResult;
use iEXPackages\WorkStatus\Enums\BaseMode;
use iEXPackages\WorkStatus\Enums\OverrideMode;

final class ScheduleOverrideResolver implements WorkStatusResolverInterface
{
    public function __construct(private readonly WorkStatusConfig $config) {}

    public function resolve(CarbonInterface $now): ?WorkStatusResult
    {
        if ($this->config->baseMode() !== BaseMode::Schedule) {
            return null;
        }

        if (!$this->config->allowOverride()) {
            return null;
        }

        $mode = $this->config->overrideMode();
        if ($mode === OverrideMode::None) {
            return null;
        }

        $until = $this->config->overrideUntil();
        if ($until !== null && $until->lessThanOrEqualTo($now)) {
            $this->config->clearOverride();
            return null;
        }

        $isOnline = match ($mode) {
            OverrideMode::ForceOnline  => true,
            OverrideMode::ForceOffline => false,
            default => true,
        };

        return new WorkStatusResult(
            isOnline: $isOnline,
            baseMode: BaseMode::Schedule,
            source: WorkStatusResult::SOURCE_MANUAL_OVERRIDE,
            reason: $this->config->overrideReason() ?? 'manual_override',
            allowOverride: true,
            overrideMode: $mode,
            overrideUntil: $until,
            scheduleRuleId: null,
            nextChangeAt: null,
            manualState: null,
        );
    }
}
