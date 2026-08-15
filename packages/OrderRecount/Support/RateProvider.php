<?php

declare(strict_types=1);

namespace iEXPackages\OrderRecount\Support;

use App\Models\Task;
use iEXPackages\Calculator\CalculatorFacade;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * RateProvider
 *
 * Отвечает за:
 * - текущий курс заявки (числовой / отображаемый)
 * - расчёт нового курса через Calculator (без изменения заявки)
 *
 * Важно:
 * - Числовой курс возвращаем строкой (для точности).
 * - Display-курс возвращаем как есть (чтобы было понятно человеку).
 */
final class RateProvider
{
    /**
     * Текущий числовой курс заявки (course_float).
     *
     * @return string|null Возвращает строку числа > 0, иначе null.
     */
    public function currentRate(Task $task): ?string
    {
        return $this->normalizeNumericString($task->course_float);
    }

    /**
     * Текущий отображаемый курс (course_display).
     *
     * @param Task $task
     * @return string|null
     */
    public function currentRateDisplay(Task $task): ?string
    {
        $v = trim((string) ($task->course_display ?? ''));
        return $v !== '' ? $v : null;
    }

    /**
     * Рассчитать новый числовой курс (getRateValue()).
     *
     * @param array<string, mixed> $options
     * @return string|null Возвращает строку числа > 0, иначе null.
     */
    public function calculateNewRate(Task $task, array $options = []): ?string
    {
        try {
            $direction = $task->direction_exchange;
            if (!$direction) {
                return null;
            }

            $calc = CalculatorFacade::setDirectionExchange($direction)
                ->setOrder($task)
                ->calculateWithOptions($options);

            return $this->normalizeNumericString($calc->getRateValue());
        } catch (Throwable $e) {
            if ((bool) config('order-recount.debug_rate_provider', false)) {
                Log::warning('OrderRecount RateProvider calculateNewRate failed', [
                    'task_id' => (int) ($task->id ?? 0),
                    'message' => $e->getMessage(),
                ]);
            }
            return null;
        }
    }

    /**
     * Рассчитать новый отображаемый курс (getFullRate()).
     *
     * @param Task $task
     * @param array<string, mixed> $options
     * @return string|null
     */
    public function calculateNewRateDisplay(Task $task, array $options = []): ?string
    {
        try {
            $direction = $task->direction_exchange;
            if (!$direction) {
                return null;
            }

            $calc = CalculatorFacade::setDirectionExchange($direction)
                ->setOrder($task)
                ->calculateWithOptions($options);

            $v = trim((string) $calc->getFullRate());
            return $v !== '' ? $v : null;
        } catch (Throwable $e) {
            if ((bool) config('order-recount.debug_rate_provider', false)) {
                Log::warning('OrderRecount RateProvider calculateNewRateDisplay failed', [
                    'task_id' => (int) ($task->id ?? 0),
                    'message' => $e->getMessage(),
                ]);
            }
            return null;
        }
    }

    /**
     * Нормализует значение в строку числа > 0.
     * Поддерживает значения вида "0.001", "  12 345.67 ", "12,34".
     */
    private function normalizeNumericString(mixed $raw): ?string
    {
        $s = trim((string) ($raw ?? ''));
        if ($s === '') {
            return null;
        }

        $s = str_replace(',', '.', $s);
        $s = preg_replace('/[^\d.]/', '', $s) ?? '';

        // оставляем одну точку
        $pos = strpos($s, '.');
        if ($pos !== false) {
            $s = substr($s, 0, $pos + 1) . str_replace('.', '', substr($s, $pos + 1));
        }

        if ($s === '' || (float) $s <= 0) {
            return null;
        }

        return $s;
    }
}
