<?php

declare(strict_types=1);

namespace iEXPackages\WorkStatus\Resolvers;

use App\Settings\WorkStatusConfig;
use Carbon\CarbonInterface;
use iEXPackages\WorkStatus\Contracts\WorkStatusResolverInterface;
use iEXPackages\WorkStatus\DTO\WorkStatusResult;
use iEXPackages\WorkStatus\Enums\BaseMode;
use iEXPackages\WorkStatus\Services\WorkScheduleEvaluator;

final class ScheduleResolver implements WorkStatusResolverInterface
{
    public function __construct(
        private readonly WorkStatusConfig $config,
        private readonly WorkScheduleEvaluator $evaluator,
    ) {}

    public function resolve(CarbonInterface $now): ?WorkStatusResult
    {
        if ($this->config->baseMode() !== BaseMode::Schedule) {
            return null;
        }

        $ev = $this->evaluator->evaluate($now);

        return new WorkStatusResult(
            isOnline: (bool) $ev['isOnline'],
            baseMode: BaseMode::Schedule,
            source: WorkStatusResult::SOURCE_SCHEDULE,
            reason: (string) ($ev['reason'] ?? 'schedule'),
            allowOverride: $this->config->allowOverride(),
            overrideMode: $this->config->overrideMode(),
            overrideUntil: $this->config->overrideUntil(),
            scheduleRuleId: $ev['ruleId'] ?? null,
            nextChangeAt: $ev['nextChangeAt'] ?? null,
            manualState: null,
        );
    }
}
