<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class IEXResetPasswordCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'iex:resetpass {--pass=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Сбросить пароль администратора';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $user = User::find(1);

        if (!$user) {
            $this->error('⚠️ Администратор с ID=1 не найден!');
            return;
        }

        $password = $this->option('pass');

        if (!$password) {
            do {
                $password = $this->secret('🔑 Введите новый пароль для администратора');

                if (empty($password)) {
                    $this->error('❌ Пароль не может быть пустым. Попробуйте снова.');
                    continue;
                }

                if (strlen($password) < 6) {
                    $this->error('❌ Пароль должен содержать не менее 6 символов.');
                    continue;
                }

                $passwordConfirm = $this->secret('🔑 Повторите новый пароль');

                if ($password !== $passwordConfirm) {
                    $this->error('❌ Введённые пароли не совпадают. Попробуйте снова.');
                    continue;
                }

                break;

            } while (true);
        } elseif ($password === 'random') {
            $password = $this->generateRandomPassword(12);
        }

        $user->update(['password' => Hash::make($password)]);

        $this->info('✅ Пароль успешно обновлён!');
        $this->line('───────────────────────────────');
        $this->line("👤 Логин: {$user->name}");
        $this->line("📧 Почта: {$user->email}");
        $this->line("🔑 Новый пароль: {$password}");
        $this->line('───────────────────────────────');
    }

    /**
     * Генерирует случайный пароль.
     *
     * @param int $length
     * @return string
     */
    private function generateRandomPassword(int $length = 12): string
    {
        return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_=+'), 0, $length);
    }
}
