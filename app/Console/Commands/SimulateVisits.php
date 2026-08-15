<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use App\Services\VisitRecorder;

class SimulateVisits extends Command
{
    protected $signature = 'visits:simulate {--count=100}';
    protected $description = 'Сымитировать визиты гостей и пользователей для теста статистики';

    public function handle(VisitRecorder $recorder): int
    {
        $count = (int) $this->option('count');

        $this->info("Создаём {$count} тестовых визитов...");

        for ($i = 0; $i < $count; $i++) {
            $request = Request::create('/', 'GET');
            $request->setUserResolver(fn() => null); // гость
            $recorder->record($request);
        }

        $this->info("Готово ✅ {$count} визитов гостей добавлены.");
        return self::SUCCESS;
    }
}
