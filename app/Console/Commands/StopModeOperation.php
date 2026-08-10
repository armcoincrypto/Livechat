<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Events\StatusSiteEvent;
use App\Support\WorkStatusFresh;
use iEXPackages\WorkStatus\Services\WorkStatusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class StopModeOperation extends Command
{
    protected $signature = 'operation:stop';

    protected $description = 'Остановка рабочего режима операторов';

    public function handle(WorkStatusService $workStatus): int
    {
        WorkStatusFresh::forgetRequestCache();

        // Manual Offline when no schedules — avoids empty-schedule snap-back surprises.
        WorkStatusFresh::applyOperatorOffline($workStatus, 'Перерыв');

        if ((int) iEXSetting('is_enabled_module_socket', 0) === 1) {
            broadcast(new StatusSiteEvent(' ушел на перерыв', 2));
        }

        Cache::forget('exchanger_client:tech_status_v1');

        // Hide public rate XMLs from monitors (empty <rates/>, 0 items).
        \App\Services\Rates\RatesXmlMonitorGate::hide('operation:stop');

        // Fresh offline check inside scheme:files (not stale middleware cache).
        WorkStatusFresh::forgetRequestCache();
        $this->call('scheme:files');

        return self::SUCCESS;
    }
}
