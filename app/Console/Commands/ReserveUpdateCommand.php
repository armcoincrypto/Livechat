<?php

namespace App\Console\Commands;

use App\Models\Reserve;
use App\Models\ReserveFile;
use App\Models\ReserveFileGroup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReserveUpdateCommand extends Command
{
    protected $signature = 'reserve:update';

    protected $description = 'Обновление файлов резерва';

    public function handle(): void
    {
        if (iEXSetting('is_enabled_reserves_from_file'))
        {
            $activeGroups = ReserveFileGroup::where('status', 1)->exists();
            if ($activeGroups) {
                $this->updateFromFiles();
            }
        }

        if (iEXSetting('is_enabled_reserves_from_server'))
        {
            $hasServerReserves = Reserve::whereNotNull('id_server_reserve')->exists();
            if ($hasServerReserves) {
                $this->updateFromServers();
            }
        }
    }

    /**
     * Обновление резервов из файлов
     */
    private function updateFromFiles(): void
    {
        $activeGroups = ReserveFileGroup::where('status', 1)->get();

        foreach ($activeGroups as $group) {
            try {
                $response = Http::timeout(10)->get($group->link);

                if (!$response->successful()) {
                    Log::error("Файл недоступен: {$group->name} ({$group->link}). Статус: {$response->status()}");
                    continue;
                }

                $lines = preg_split("/\r\n|\n|\r/", trim($response->body()));

                if (empty($lines)) {
                    Log::warning("Файл {$group->name} пуст.");
                    continue;
                }

                $parsedData = [];

                foreach ($lines as $lineNumber => $line) {
                    if (empty(trim($line))) {
                        continue; // Пропускаем пустые строки
                    }

                    if (!str_contains($line, ':')) {
                        Log::warning("Строка без разделителя ':' в файле {$group->name} (строка {$lineNumber}): {$line}");
                        continue;
                    }

                    [$key, $value] = explode(':', $line, 2);
                    $key = trim($key);
                    $value = trim($value);

                    if ($key === '' || $value === '') {
                        Log::warning("Некорректная строка в файле {$group->name} (строка {$lineNumber}): {$line}");
                        continue;
                    }

                    $parsedData[$key] = $value;
                }

                if (empty($parsedData)) {
                    Log::warning("Нет корректных данных для обновления из файла {$group->name}.");
                    continue;
                }

                ReserveFile::where('id_group', $group->id)
                    ->where('status', 1)
                    ->chunkById(100, function ($files) use ($parsedData, $group) {
                        foreach ($files as $file) {
                            if (!isset($parsedData[$file->name])) {
                                Log::warning("Отсутствует запись '{$file->name}' в файле группы {$group->name}.");
                                continue;
                            }

                            if (!is_numeric($parsedData[$file->name])) {
                                Log::warning("Некорректное числовое значение для '{$file->name}' в файле группы {$group->name}: {$parsedData[$file->name]}");
                                continue;
                            }

                            $file->update(['amount' => (float)$parsedData[$file->name]]);
                        }
                    });

            } catch (\Throwable $e) {
                Log::error("Ошибка обновления из файла '{$group->name}': {$e->getMessage()}");
            }
        }

        // Обновляем сами резервы из данных файлов
        $this->syncReservesWithFiles();
    }

    /**
     * Синхронизация основных резервов с файлами
     */
    private function syncReservesWithFiles(): void
    {
        // Получаем актуальные резервы из файлов
        $fileReserves = ReserveFile::where('status', 1)
            ->whereNotNull('amount')
            ->pluck('amount', 'id');

        if ($fileReserves->isEmpty()) {
            Log::warning('Отсутствуют актуальные резервы в файлах для синхронизации.');
            return;
        }

        // Обновляем только существующие резервы
        Reserve::whereIn('id_file_reserve', $fileReserves->keys())
            ->chunkById(100, function ($reserves) use ($fileReserves) {
                foreach ($reserves as $reserve) {
                    $amount = $fileReserves[$reserve->id_file_reserve] ?? null;

                    if (is_numeric($amount)) {
                        $reserve->update(['summa' => (float)$amount]);
                    } else {
                        Log::warning("Некорректное значение резерва для Reserve ID {$reserve->id}: {$amount}");
                    }
                }
            });
    }

    /**
     * Обновление резервов с серверов
     */
    private function updateFromServers(): void
    {
        $serverGroups = Reserve::whereNotNull('id_server_reserve')
            ->get()
            ->map(function ($item) {
                return explode('_', $item->id_server_reserve);
            })
            ->filter(fn ($parts) => count($parts) === 3)
            ->map(fn ($parts) => [$parts[0], $parts[1]])
            ->unique()
            ->values();

        if ($serverGroups->isEmpty()) {
            Log::warning('Отсутствуют группы серверов для обновления резервов.');
            return;
        }

        $balances = [];

        foreach ($serverGroups as [$gateway, $account]) {
            try {
                $balances["{$gateway}_{$account}"] = \iEXPackages\Payment\PaymentFacade::pay($gateway, $account)
                    ->api()
                    ->getAllBalances();
            } catch (\Throwable $e) {
                Log::error("Ошибка получения баланса с сервера ({$gateway}_{$account}): {$e->getMessage()}");
            }
        }

        if (empty($balances)) {
            Log::warning('Не удалось получить балансы ни с одного сервера.');
            return;
        }

        Reserve::whereNotNull('id_server_reserve')
            ->chunkById(100, function ($reserves) use ($balances) {
                foreach ($reserves as $reserve) {
                    $parts = explode('_', $reserve->id_server_reserve);

                    if (count($parts) !== 3) {
                        Log::warning("Некорректный формат id_server_reserve у резерва ID {$reserve->id}: {$reserve->id_server_reserve}");
                        continue;
                    }

                    [$gateway, $account, $currency] = $parts;
                    $key = "{$gateway}_{$account}";
                    $currency = strtoupper(trim($currency));

                    if (isset($balances[$key][$currency]) && is_numeric($balances[$key][$currency])) {
                        $reserve->update(['summa' => (float)$balances[$key][$currency]]);
                    } else {
                        Log::warning("Баланс валюты '{$currency}' отсутствует или некорректен на сервере '{$key}' для резерва ID {$reserve->id}.");
                    }
                }
            });
    }
}
