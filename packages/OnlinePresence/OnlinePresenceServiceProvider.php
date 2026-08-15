<?php
declare(strict_types=1);

namespace iEXPackages\OnlinePresence;

use iEXPackages\OnlinePresence\Console\OnlineSimulateCommand;
use Illuminate\Support\ServiceProvider;
use iEXPackages\OnlinePresence\Console\PresencePruneCommand;
use iEXPackages\OnlinePresence\Console\PresenceRollupDailyCommand;
use iEXPackages\OnlinePresence\Console\PresenceSnapshotCommand;

final class OnlinePresenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
      //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                PresenceSnapshotCommand::class,
                PresenceRollupDailyCommand::class,
                PresencePruneCommand::class,
                OnlineSimulateCommand::class
            ]);
        }
    }
}
