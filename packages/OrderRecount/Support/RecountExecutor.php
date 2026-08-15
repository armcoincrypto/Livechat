<?php

declare(strict_types=1);

namespace iEXPackages\OrderRecount\Support;

use App\Models\Task;
use iEXPackages\Transaction\Facades\TransactionFacade;

/**
 * RecountExecutor
 *
 * Выполняет реальный пересчёт заявки через Transaction engine.
 *
 * Важно:
 * - Работает в cron/jobs (без Request).
 * - Суммы нормализуются (на случай пробелов/запятых).
 * - atRate ограничен допустимыми режимами.
 */
final class RecountExecutor
{
    /**
     * Выполнить пересчёт заявки.
     *
     * @param Task $task
     * @param int $atRate
     *   0 — авто (используем текущий курс заявки)
     *   1 — пересчитать по текущим правилам (стандартный режим)
     *   2 — фиксированный курс (у тебя это final_adjustment = -100)
     *   3 — ручной курс (default_rate = $manual)
     *   4 — ручная корректировка (final_adjustment = $manual)
     * @param float $give Сумма "отдал клиент". Если <=0 — берём из заявки (getAmountIn()).
     * @param string $manual Для режимов 3/4.
     * @throws \Throwable
     */
    public function recount(Task $task, int $atRate = 1, float $give = 0.0, string $manual = '0'): void
    {
        $atRate = $this->normalizeAtRate($atRate);

        $tx = TransactionFacade::init($task);

        if ($give <= 0) {
            $give = $this->toFloat($tx->getAmountIn());
        }

        if ($give <= 0) {
            // Нечего пересчитывать
            return;
        }

        $tx->recount($atRate, $give, $manual);
    }

    /**
     * Разрешаем только известные режимы.
     */
    private function normalizeAtRate(int $atRate): int
    {
        return match ($atRate) {
            0, 1, 2, 3, 4 => $atRate,
            default => 1,
        };
    }

    /**
     * Приводит строку/число к float безопасно для форматов "12 345,67".
     * Использовать ТОЛЬКО для логики/валидации, не для хранения денег.
     */
    private function toFloat(string|int|float|null $raw): float
    {
        return normalize_amount_to_float($raw);
    }
}
