<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\JobSchedule;
use iEXPackages\WorkStatus\Enums\ManualState;
use iEXPackages\WorkStatus\Enums\OverrideMode;
use iEXPackages\WorkStatus\Services\WorkStatusService;

/**
 * Fresh work-status reads that ignore the per-request WorkStatusMiddleware cache.
 *
 * Admin "Настройка режима" runs operation:start/stop via Artisan::call() inside an
 * HTTP request. The middleware freezes work_status at request start, so
 * work_is_offline() stays stale and scheme:files can re-hide the feed right after
 * a successful start. Always use this helper inside those commands.
 */
final class WorkStatusFresh
{
    public static function forgetRequestCache(): void
    {
        if (! app()->bound('request')) {
            return;
        }

        try {
            request()->attributes->remove('work_status');
        } catch (\Throwable) {
            // ignore — CLI / early bootstrap
        }
    }

    public static function refreshRequestCache(): void
    {
        if (! app()->bound('request')) {
            return;
        }

        try {
            request()->attributes->set(
                'work_status',
                app(WorkStatusService::class)->getStatus()
            );
        } catch (\Throwable) {
            // ignore
        }
    }

    public static function isOffline(): bool
    {
        self::forgetRequestCache();

        return app(WorkStatusService::class)->isOffline();
    }

    public static function isOnline(): bool
    {
        return ! self::isOffline();
    }

    /**
     * Empty job_schedules evaluate to OFFLINE. Admin toggles must not depend on
     * a ForceOnline override that can be cleared and snap the site offline.
     */
    public static function hasActiveSchedules(): bool
    {
        return JobSchedule::query()->exists();
    }

    public static function applyOperatorOnline(WorkStatusService $workStatus, string $reason = 'Готов к приёму заявок'): void
    {
        self::forgetRequestCache();

        if (! self::hasActiveSchedules()) {
            // Stable: manual online cannot fall through to empty-schedule offline.
            $workStatus->switchToManual(ManualState::Online, $reason);
            self::refreshRequestCache();

            return;
        }

        $status = $workStatus->getStatus();
        if (! $status->allowOverride) {
            $workStatus->switchToSchedule(true);
        }
        $workStatus->setScheduleOverride(OverrideMode::ForceOnline, null, $reason);
        self::refreshRequestCache();
    }

    public static function applyOperatorOffline(WorkStatusService $workStatus, string $reason = 'Перерыв'): void
    {
        self::forgetRequestCache();

        if (! self::hasActiveSchedules()) {
            $workStatus->switchToManual(ManualState::Offline, $reason);
            self::refreshRequestCache();

            return;
        }

        $status = $workStatus->getStatus();
        if (! $status->allowOverride) {
            $workStatus->switchToSchedule(true);
        }
        $workStatus->setScheduleOverride(OverrideMode::ForceOffline, null, $reason);
        self::refreshRequestCache();
    }
}
