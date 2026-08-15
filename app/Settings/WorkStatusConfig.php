<?php

declare(strict_types=1);

namespace App\Settings;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use iEXPackages\DynamicConfig\DynamicConfigModel;
use iEXPackages\WorkStatus\Enums\BaseMode;
use iEXPackages\WorkStatus\Enums\ManualState;
use iEXPackages\WorkStatus\Enums\OverrideMode;

/**
 * WorkStatusConfig
 *
 * Обёртка над DynamicConfig для настроек режима работы.
 *
 * Префикс: work_status.*
 * Scope:   global
 */
final class WorkStatusConfig extends DynamicConfigModel
{
    protected function prefix(): string
    {
        return 'work_status';
    }

    protected function scopeType(): string
    {
        return 'global';
    }

    protected function scopeId(): ?int
    {
        return null;
    }

    /**
     * @return list<string>
     */
    protected function fields(): array
    {
        return [
            'base_mode',
            'manual_state',
            'allow_override',
            'override_mode',
            'override_until',
            'override_reason',
        ];
    }

    public function baseMode(): BaseMode
    {
        return BaseMode::fromValue($this->getString('base_mode', BaseMode::Schedule->value));
    }

    public function manualState(): ManualState
    {
        return ManualState::fromValue($this->getString('manual_state', ManualState::Online->value));
    }

    public function allowOverride(): bool
    {
        return $this->getBool('allow_override', true);
    }

    public function overrideMode(): OverrideMode
    {
        return OverrideMode::fromValue($this->getString('override_mode', OverrideMode::None->value));
    }

    public function overrideUntil(): ?CarbonInterface
    {
        $raw = trim((string) $this->getString('override_until', ''));
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    public function overrideReason(): ?string
    {
        $v = trim((string) $this->getString('override_reason', ''));
        return $v === '' ? null : $v;
    }

    public function setBaseMode(BaseMode $mode): void
    {
        parent::update(['base_mode' => $mode->value]);
    }

    public function setManualState(ManualState $state, ?string $reason = null): void
    {
        $payload = ['manual_state' => $state->value];

        if ($reason !== null) {
            $payload['override_reason'] = trim($reason);
        }

        parent::update($payload);
    }

    public function setAllowOverride(bool $allow): void
    {
        parent::update(['allow_override' => $allow ? 1 : 0]);
    }

    public function setOverride(OverrideMode $mode, ?CarbonInterface $until = null, ?string $reason = null): void
    {
        parent::update([
            'override_mode'   => $mode->value,
            'override_until'  => $until ? Carbon::instance($until)->toDateTimeString() : null,
            'override_reason' => $reason !== null ? trim($reason) : null,
        ]);
    }

    public function clearOverride(): void
    {
        $this->setOverride(OverrideMode::None, null, null);
    }
}
