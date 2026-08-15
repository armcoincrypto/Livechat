<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Facades\iEXApp;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class TelegramStatisticsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'iextelegram:stat';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     *
     */
    public function handle(): void
    {
        iEXApp::telegramNotificationForChannel('send_statistics');
    }
}
