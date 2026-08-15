<?php

namespace App\Console\Commands;

use App\Models\HistoryUpdatedData;
use App\Models\ReferralStatistics;
use App\Models\TaskStatusLog;
use App\Models\VerificationCard;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ClearLogsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'log:clears';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Очистка логов';

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
     * @return mixed
     */
    public function handle()
    {
        $logsSettings = [
            TaskStatusLog::class => (int) iEXSetting('settings_log_old_status_clear'),
            ReferralStatistics::class => (int) iEXSetting('settings_log_partners_transition_clear'),
            VerificationCard::class => [
                'days' => (int) iEXSetting('settings_log_unverified_card'),
                'conditions' => ['status' => 3]
            ],
            HistoryUpdatedData::class => 10, // Статичное значение 10 дней
        ];

        foreach ($logsSettings as $model => $config) {
            if (is_array($config)) {
                $days = $config['days'] ?? 0;
                $conditions = $config['conditions'] ?? [];
            } else {
                $days = $config;
                $conditions = [];
            }

            if ($days > 0) {
                $this->clearOldLogs($model, $days, $conditions);
            }
        }
    }

    /**
     * Удаление старых логов по заданной модели, дням и дополнительным условиям.
     *
     * @param string $model
     * @param int $days
     * @param array $conditions
     * @return void
     */
    protected function clearOldLogs(string $model, int $days, array $conditions = []): void
    {
        $query = $model::query()->whereDate('created_at', '<=', Carbon::now()->subDays($days));

        if (!empty($conditions)) {
            $query->where($conditions);
        }

        $query->delete();
    }
}
