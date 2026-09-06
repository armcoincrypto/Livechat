<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Auth\UserAccountLifecycleService;
use iEXPackages\Update\Facades\UpdateClientFacade;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class MonitoringDailyCommand extends Command
{
    protected $signature = 'monitoring:daily {--dry-run : Report eligible counts without mutating}';
    protected $description = 'Ежедневная обработка данных (обновления, деактивация пользователей)';

    public function handle(UserAccountLifecycleService $lifecycle): void
    {
        $this->info('🚀 Запуск ежедневной обработки данных...');

        $this->updateCheck();
        $this->deactivateInactiveUsers($lifecycle);

        $this->info('✅ Ежедневная обработка данных завершена.');
    }

    /**
     * Обработка деактивации неактивных пользователей.
     */
    protected function deactivateInactiveUsers(UserAccountLifecycleService $lifecycle): void
    {
        $this->deactivateUnverifiedUsers($lifecycle);
        $this->deactivateUsersWithNoActivity();
    }

    /**
     * Деактивирует неподтверждённых пользователей спустя заданное количество дней после регистрации.
     * Accounts with meaningful customer history (orders, logins, financial rows) are excluded.
     */
    protected function deactivateUnverifiedUsers(UserAccountLifecycleService $lifecycle): void
    {
        $days = (int) iEXSetting('new_user_registration_cleanup_days');

        if ($days <= 0) {
            $this->warn('⚠️ Деактивация неподтверждённых пользователей отключена (дни не заданы).');
            return;
        }

        $query = $lifecycle->scopeEligibleForUnverifiedAutoDeactivation(
            User::query(),
            $days,
        );

        $eligible = (clone $query)->count();
        $this->info("🟡 Кандидатов на деактивацию (неподтверждённые без истории): {$eligible}");

        if ($this->option('dry-run')) {
            $this->warn('DRY_RUN=1 — деактивация неподтверждённых не применена.');
            return;
        }

        $affected = $query->update(['deactivation' => 1]);

        $this->info("🟡 Деактивировано неподтверждённых пользователей: {$affected}");
    }

    /**
     * Деактивирует пользователей, не проявлявших активности определённое количество дней.
     */
    protected function deactivateUsersWithNoActivity(): void
    {
        $days = (int) iEXSetting('auto_disabled_user_account');

        if ($days <= 0) {
            $this->warn('⚠️ Деактивация пользователей без активности отключена (дни не заданы).');
            return;
        }

        $thresholdDate = Carbon::now()->subDays($days);

        // Keep inactivity cleanup, but never auto-deactivate users who still have
        // order/financial footprint — same protection class as unverified cleanup.
        $query = User::where('last_activity_at', '<', $thresholdDate)
            ->where('deactivation', '!=', 1)
            ->doesntHave('roles')
            ->where('is_guest', 0)
            ->whereDoesntHave('tasks')
            ->whereDoesntHave('internalAccounts', function ($accounts) {
                $accounts->where('balance', '>', 0);
            })
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('history_internal_accounts')
                    ->whereColumn('history_internal_accounts.id_user', 'users.id');
            });

        $eligible = (clone $query)->count();
        $this->info("🟠 Кандидатов на деактивацию (без активности и без истории): {$eligible}");

        if ($this->option('dry-run')) {
            $this->warn('DRY_RUN=1 — деактивация по неактивности не применена.');
            return;
        }

        $affected = $query->update(['deactivation' => 1]);

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
        if ($this->option('dry-run')) {
            $this->info('📡 DRY_RUN — пропуск updateCheck.');
            return;
        }

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
