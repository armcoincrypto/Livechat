<?php

declare(strict_types=1);

namespace iEXPackages\WorkStatus\DTO;

use Carbon\CarbonInterface;
use iEXPackages\WorkStatus\Enums\BaseMode;
use iEXPackages\WorkStatus\Enums\ManualState;
use iEXPackages\WorkStatus\Enums\OverrideMode;

/**
 * WorkStatusResult
 *
 * Итог вычисления статуса работы.
 *
 * @phpstan-type Source 'manual'|'schedule'|'manual_override'
 */
final readonly class WorkStatusResult
{
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_SCHEDULE = 'schedule';
    public const SOURCE_MANUAL_OVERRIDE = 'manual_override';

    /**
     * @param Source $source Источник решения (для UI/отладки).
     */
    public function __construct(
        public bool $isOnline,
        public BaseMode $baseMode,
        public string $source,
        public ?string $reason = null,

        public bool $allowOverride = false,
        public OverrideMode $overrideMode = OverrideMode::None,
        public ?CarbonInterface $overrideUntil = null,

        public ?int $scheduleRuleId = null,
        public ?CarbonInterface $nextChangeAt = null,

        public ?ManualState $manualState = null,
    ) {}
}
