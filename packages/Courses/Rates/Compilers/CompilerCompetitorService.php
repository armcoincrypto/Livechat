<?php
declare(strict_types=1);

namespace iEXPackages\Courses\Rates\Compilers;

use App\Models\CompetitorLink;
use App\Models\CompetitorRates;
use iEXPackages\Courses\Services\CompetitorParserService;
use iEXPackages\Courses\Services\RatesLoggerService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class CompilerCompetitorService
{
    protected RatesLoggerService $logger;

    public function __construct(RatesLoggerService $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Обработчик курсов "Курсы конкурентов"
     */
    public function handle(): void
    {
        // Используем cursor() для экономии памяти при работе с большими данными
        $links = CompetitorLink::cursor();

        foreach ($links as $link) {
            try {
                $parser = new CompetitorParserService($link->link);
                $response = $parser->getAllRates();

                $updates = []; // Для массового обновления курсов

                foreach ($link->rates as $value) {
                    if ($value->status != 1) {
                        continue;
                    }

                    $key = Str::upper($value->name);

                    // Проверяем, существует ли ключ в ответе API
                    if (!isset($response[$key])) {
                        Log::warning("Пропущено: нет курса для {$key}");
                        continue;
                    }

                    // Добавляем обновление в массив
                    $updates[] = [
                        'id' => $value->id,
                        'value' => 1,
                        'summa' => (float) $response[$key],
                        'updated_at' => now(),
                    ];

                    // Если включена история курсов, добавляем запись в лог
                    $this->logger->batchLog('competitor', $value->id, $key, (string) $value->summa, (string) $response[$key]);
                }

                // Массовое обновление курсов (ускоряет работу)
                if (!empty($updates)) {
                    CompetitorRates::upsert($updates, ['id'], ['summa', 'updated_at']);
                }

                $this->logger->flush();

            } catch (Throwable $exception) {
                Log::error("Ошибка обновления курсов конкурентов: " . $exception->getMessage(), ['competitor' => $link->name]);
            }
        }
    }
}
