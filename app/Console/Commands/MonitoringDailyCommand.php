<?php

namespace App\Console\Commands;

use App\Models\User;
use iEXPackages\Update\Facades\UpdateClientFacade;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class MonitoringDailyCommand extends Command
{
    protected $signature = 'monitoring:daily';
    protected $description = 'Ежедневная обработка данных (обновления, деактивация пользователей)';

    public function handle(): void
    {
        $this->info('🚀 Запуск ежедневной обработки данных...');

        $this->updateCheck();
        $this->deactivateInactiveUsers();

        $this->info('✅ Ежедневная обработка данных завершена.');
    }

    /**
     * Обработка деактивации неактивных пользователей.
     */
    protected function deactivateInactiveUsers(): void
    {
        $this->deactivateUnverifiedUsers();
        $this->deactivateUsersWithNoActivity();
    }

    /**
     * Деактивирует неподтверждённых пользователей спустя заданное количество дней после регистрации.
     */
    protected function deactivateUnverifiedUsers(): void
    {
        $days = (int)iEXSetting('new_user_registration_cleanup_days');

        if ($days <= 0) {
            $this->warn('⚠️ Деактивация неподтверждённых пользователей отключена (дни не заданы).');
            return;
        }

        $thresholdDate = Carbon::now()->subDays($days);

        $affected = User::whereNull('email_verified_at')
            ->where('created_at', '<', $thresholdDate)
            ->where('deactivation', '!=', 1)
            ->doesntHave('roles')
            ->where('is_guest', 0)
            ->update(['deactivation' => 1]);

        $this->info("🟡 Деактивировано неподтверждённых пользователей: {$affected}");
    }

    /**
     * Деактивирует пользователей, не проявлявших активности определённое количество дней.
     */
    protected function deactivateUsersWithNoActivity(): void
    {
        $days = (int)iEXSetting('auto_disabled_user_account');

        if ($days <= 0) {
            $this->warn('⚠️ Деактивация пользователей без активности отключена (дни не заданы).');
            return;
        }

        $thresholdDate = Carbon::now()->subDays($days);

        $affected = User::where('last_activity_at', '<', $thresholdDate)
            ->where('deactivation', '!=', 1)
            ->doesntHave('roles')
            ->where('is_guest', 0)
            ->update(['deactivation' => 1]);

        $this->info("🟠 Деактивировано пользователей без активности: {$affected}");

        // Повторная активация гостевых аккаунтов, если были ранее отключены
        $reactivatedGuests = User::where('is_guest', 1)
            ->where('deactivation', 1)
            ->update(['deactivation' => 0]);

        if ($reactivatedGuests > 0) {
            $this->info("🔵 Повторно активировано гостевых аккаунтов: {$reactivatedGuests}");
        }
    }

    /**
     * Проверка обновлений и генерация файла с информацией о проекте.
     */
    protected function updateCheck(): void
    {
        $this->info('📡 Проверка наличия обновлений...');

        UpdateClientFacade::checkUpdates();

        $configPath = public_path('project_info.txt');

        File::put($configPath, json_encode([
            'product'           => 'iEXExchanger',
            'website'           => 'https://iexexchanger.com',
            'version'           => config('iexexchanger.version.current'),
            'framework_version' => app()->version(),
            'developer'         => 'iEXExchanger group',
            'generated_at'      => Carbon::now()->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $this->info('📄 Файл с информацией о проекте успешно создан.');
    }
}
