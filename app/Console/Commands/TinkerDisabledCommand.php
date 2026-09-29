<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Production builds do not ship laravel/tinker. Ops/agent probes still invoke
 * `php artisan tinker`, which logged as ERROR CommandNotFoundException.
 * This stub absorbs those calls with a clear message and non-error exit.
 */
final class TinkerDisabledCommand extends Command
{
    protected $signature = 'tinker {--execute= : Ignored; tinker is disabled in production}';

    protected $description = 'Disabled in production (laravel/tinker not installed). Use php8.4 -r with bootstrap instead.';

    public function handle(): int
    {
        $this->warn('artisan tinker is disabled on Exswaping production builds.');
        $this->line('Use: sudo -u app_exswapin_usr php8.4 -r \'require "vendor/autoload.php"; ...\'');

        return self::SUCCESS;
    }
}
