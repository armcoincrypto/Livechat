<?php

namespace App\Jobs;

use App\Models\ParserFormulaRates;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ParserFormulaUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
    }

    public function handle(): void
    {
        $from = cache()->pull('parser_formula_code_from');
        $to = cache()->pull('parser_formula_code_to');

        if (empty($from) || empty($to)) {
            Log::warning('ParserFormulaUpdateJob: Нет данных для замены ключей в формуле', compact('from', 'to'));
            return;
        }

        try {
            ParserFormulaRates::query()
                ->where('name', 'like', '%' . $from . '%')
                ->chunkById(500, function ($items) use ($from, $to) {
                    foreach ($items as $item) {
                        $newName = Str::replace($from, $to, $item->name);

                        if ($newName !== $item->name) {
                            $item->update(['name' => $newName]);
                        }
                    }
                });

            Log::info('ParserFormulaUpdateJob: Успешно выполнена замена ключей', compact('from', 'to'));

        } catch (\Throwable $e) {
            Log::error('ParserFormulaUpdateJob: Ошибка при замене ключей', [
                'message' => $e->getMessage(),
                'from' => $from,
                'to' => $to
            ]);
        }
    }
}
