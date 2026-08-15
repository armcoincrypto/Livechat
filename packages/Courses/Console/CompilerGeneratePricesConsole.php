<?php

declare(strict_types=1);

namespace iEXPackages\Courses\Console;

use App\Models\DirectionExchange;
use App\Settings\DirectionConfig;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use iEXPackages\Calculator\Traits\InteractsWithNumbers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * CompilerGeneratePricesConsole
 *
 * Назначение:
 * - Генерировать min_price1 / max_price1 на основе min_price2 / max_price2 и текущего курса направления.
 *
 * Модель расчёта:
 * - min_price1 = min_price2 / course_value
 * - max_price1 = max_price2 / course_value
 *
 * Важные правила:
 * - Если курс пустой/некорректный/<=0 — направление пропускаем.
 * - Если включён ручной режим (is_manual_*) — соответствующее поле не меняем.
 * - Все операции выполняются с высокой точностью через InteractsWithNumbers и BigDecimal
 *   (без float-ошибок и без scientific notation на выходе).
 */
final class CompilerGeneratePricesConsole extends Command
{
    use InteractsWithNumbers;

    /**
     * Имя и подпись команды.
     *
     * @var string
     */
    protected $signature = 'compiler:generate_prices';

    /**
     * Описание команды.
     *
     * @var string
     */
    protected $description = 'Генерация мин./макс. цен (min_price1/max_price1) по текущему курсу направления';

    /**
     * Выполнить команду.
     *
     * Поведение:
     * - Читает настройки DirectionConfig (generateMinPrice/generateMaxPrice).
     * - Обрабатывает направления чанками.
     * - Сохраняет изменения батчами через upsert.
     *
     * @return int
     */
    public function handle(): int
    {
        // Защита от параллельного запуска (частая причина “два раза”).
        $lock = Cache::lock('iex:courses:generate_prices:lock', 600);
        if (!$lock->get()) {
            $this->warn('Генерация цен уже выполняется — пропуск');
            return self::SUCCESS;
        }

        try {
            /** @var DirectionConfig $directionConfig */
            $directionConfig = app(DirectionConfig::class);

            $generateMinPrice = (bool) $directionConfig->generateMinPrice();
            $generateMaxPrice = (bool) $directionConfig->generateMaxPrice();

            if (!$generateMinPrice && !$generateMaxPrice) {
                $this->warn('Генерация мин./макс. цен отключена в настройках.');
                return self::SUCCESS;
            }

            $this->info('Запуск генерации min/max цен...');

            DirectionExchange::query()
                ->select([
                    'id',
                    'course_value',
                    'min_price2',
                    'max_price2',
                    'is_manual_min_price1',
                    'is_manual_max_price1',
                ])
                ->with('currency1:id,number_format')
                ->where('status', 1)
                ->chunkById(1000, function ($exchanges) use ($generateMinPrice, $generateMaxPrice): void {
                    $updates = [];

                    foreach ($exchanges as $exchange) {
                        try {
                            // Нормализуем курс и проверяем, что он > 0
                            $course = $this->sanitizeNumber($exchange->course_value, 18);

                            if ($this->compareValues($course, '0', 18) <= 0) {
                                Log::warning('Нулевой/некорректный курс направления — пропуск', [
                                    'direction_id' => (int) $exchange->id,
                                    'course_value' => (string) $exchange->course_value,
                                ]);
                                continue;
                            }

                            $nf = (int) ($exchange->currency1->number_format ?? 2);

                            $row = ['id' => (int) $exchange->id];

                            // min_price1
                            if (
                                $generateMinPrice
                                && (int) ($exchange->is_manual_min_price1 ?? 0) !== 1
                                && $exchange->min_price2 !== null
                                && $this->compareValues($this->sanitizeNumber($exchange->min_price2, 18), '0', 18) > 0
                            ) {
                                $min2 = $this->sanitizeNumber($exchange->min_price2, 18);

                                $min1 = $this->divideDecimal($min2, $course, $nf);

                                $row['min_price1'] = $min1;
                            }

                            // max_price1
                            if (
                                $generateMaxPrice
                                && (int) ($exchange->is_manual_max_price1 ?? 0) !== 1
                                && $exchange->max_price2 !== null
                                && $this->compareValues($this->sanitizeNumber($exchange->max_price2, 18), '0', 18) > 0
                            ) {
                                $max2 = $this->sanitizeNumber($exchange->max_price2, 18);

                                $max1 = $this->divideDecimal($max2, $course, $nf);

                                $row['max_price1'] = $max1;
                            }

                            // Если ничего не меняем — не добавляем update
                            if (count($row) > 1) {
                                $row['updated_at'] = now();
                                $updates[] = $row;
                            }
                        } catch (Throwable $e) {
                            Log::error('Ошибка генерации цен направления', [
                                'direction_id' => (int) $exchange->id,
                                'message' => $e->getMessage(),
                            ]);
                            $this->error($e->getMessage());
                        }
                    }

                    if ($updates !== []) {
                        DirectionExchange::upsert(
                            $updates,
                            ['id'],
                            ['min_price1', 'max_price1', 'updated_at']
                        );

                        $this->info('Обновлено направлений: ' . count($updates));
                    }
                });

            $this->info('Генерация min/max цен завершена.');

            return self::SUCCESS;
        } finally {
            try { $lock->release(); } catch (Throwable) {}
        }
    }

    /**
     * Поделить два числа в десятичной строке и вернуть строку с нужной точностью.
     *
     * Важно:
     * - Никаких float.
     * - Округление вниз (DOWN), как принято в финансовых сценариях “не завышать”.
     * - На выходе строка без scientific notation.
     *
     * @param string $dividend Нормализованное число (десятичная строка).
     * @param string $divider Нормализованное число (десятичная строка), строго > 0.
     * @param int $scale Сколько знаков после запятой вернуть.
     *
     * @return string
     */
    private function divideDecimal(string $dividend, string $divider, int $scale): string
    {
        $scale = max(0, min($scale, 18));

        // Защита от деления на 0 (хотя выше мы это уже проверили)
        if ($this->compareValues($divider, '0', 18) <= 0) {
            return '0';
        }

        $res = BigDecimal::of($dividend)
            ->dividedBy(BigDecimal::of($divider), $scale, RoundingMode::DOWN)
            ->__toString();

        // Убираем хвостовые нули и точку
        if (str_contains($res, '.')) {
            $res = rtrim(rtrim($res, '0'), '.');
        }

        return $res === '' ? '0' : $res;
    }
}
