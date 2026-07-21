<?php

declare(strict_types=1);

namespace iEXPackages\Courses\Console;

use App\Models\HistoryUpdatedData;
use App\Services\Rates\IndependentMarketBaseline;
use iEXPackages\Courses\Rates\Rates;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * UpdateCoursesConsole
 *
 * Назначение:
 * - Запуск полного обновления курсов и пересчёта направлений из консоли.
 *
 * Ключевые требования:
 * - Команда должна быть устойчивой: ошибки не должны “ронять” процесс без записи истории.
 * - Команда не должна выполняться параллельно: повторный запуск должен корректно пропускаться.
 * - Время выполнения и статистика должны сохраняться в HistoryUpdatedData.
 */
final class UpdateCoursesConsole extends Command
{
    /**
     * Подпись команды.
     *
     * Параметры:
     * - --isUpdate=true|false
     *   Если false и обменник выключен — обновление не выполняется.
     *
     * @var string
     */
    protected $signature = 'compiler:courses {--isUpdate=true}';

    /**
     * Описание команды.
     *
     * @var string
     */
    protected $description = 'Полное обновление курсов и пересчёт направлений';

    /**
     * Выполнить команду консоли.
     *
     * Поведение:
     * - Если обновление запрещено и обменник выключен — выходим без ошибок.
     * - Ставим lock на время выполнения, чтобы команда не запускалась параллельно.
     * - Выполняем Rates::fullUpdate() и пишем статистику в HistoryUpdatedData.
     *
     * @return int Код завершения команды
     */
    public function handle(): int
    {
        // Защита от параллельного запуска (самая частая причина “выполняется два раза”).
        $lock = Cache::lock('iex:courses:update:lock', 40);

        if (!$lock->get()) {
            $this->warn('Обновление курсов уже выполняется — пропуск');
            return self::SUCCESS;
        }

        $startedAt = microtime(true);
        $ownsSnapshot = IndependentMarketBaseline::currentSnapshot() === null;
        if ($ownsSnapshot) {
            IndependentMarketBaseline::beginSnapshot(purpose: 'rate_update');
        }

        try {
            $isUpdate = $this->normalizeBoolOption($this->option('isUpdate'));

            // Отключаем обновление курсов, если обменник отключен (по твоей логике).
            if ($isUpdate === false && work_is_offline()) {
                $this->warn('Курсы не обновлены: обменник отключен');
                return self::SUCCESS;
            }

            $this->comment('Запуск процесса обновления курсов...');

            /** @var Rates $rates */
            $rates = app(Rates::class)->setCommand($this);
            $rates->fullUpdate();

            $timeSec = round(microtime(true) - $startedAt, 4);

            $last = HistoryUpdatedData::query()
                ->where('type_update', 'courses')
                ->latest()
                ->first();

            HistoryUpdatedData::create([
                'time' => $timeSec,
                'type_update' => 'courses',
                'count_num' => $rates->getCountUpdateData(),
                'total_num' => $rates->getCountTotalUpdate(),
                'old_time' => $last?->time ?? 0,
            ]);

            $this->info('Время обновления: ' . $timeSec . ' сек.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $timeSec = round(microtime(true) - $startedAt, 4);

            $this->error('Ошибка обновления курсов: ' . $e->getMessage());
            $this->error('Время до ошибки: ' . $timeSec . ' сек.');

            Log::error('compiler:courses failed', [
                'message' => $e->getMessage(),
            ]);

            // Пишем историю даже при ошибке (так проще анализировать стабильность).
            try {
                $last = HistoryUpdatedData::query()
                    ->where('type_update', 'courses')
                    ->latest()
                    ->first();

                HistoryUpdatedData::create([
                    'time' => $timeSec,
                    'type_update' => 'courses',
                    'count_num' => 0,
                    'total_num' => 0,
                    'old_time' => $last?->time ?? 0,
                ]);
            } catch (Throwable $historyError) {
                Log::error('Failed to write HistoryUpdatedData', [
                    'message' => $historyError->getMessage(),
                ]);
            }

            return self::FAILURE;
        } finally {
            if ($ownsSnapshot) {
                IndependentMarketBaseline::endSnapshot();
            }
            try {
                $lock->release();
            } catch (Throwable) {
                // Ничего не делаем, lock сам истечёт по TTL.
            }
        }
    }

    /**
     * Нормализовать значение опции в bool.
     *
     * Поддерживаем:
     * - true/false
     * - 1/0
     * - "true"/"false"
     * - "yes"/"no"
     * - "on"/"off"
     *
     * @param mixed $value
     * @return bool
     */
    private function normalizeBoolOption(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $s = strtolower(trim((string) $value));

        if (in_array($s, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($s, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        // По умолчанию: true (чтобы команда не “молчала” из-за неожиданных значений)
        return true;
    }
}
