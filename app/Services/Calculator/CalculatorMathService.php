<?php
declare(strict_types=1);

namespace App\Services\Calculator;

use Illuminate\Support\Facades\Log;

class CalculatorMathService
{
    // Регулярное выражение для очистки выражений
    private const EXPRESSION_REGEX = '/[^0-9.%]/';

    // Константа точности расчётов (число знаков после запятой)
    private const DEFAULT_PRECISION = 18;

    // Текущее значение суммы
    private string $summa;

    // Конфигурация калькулятора
    private CalculatorMathConfigInterface $config;

    /**
     * Конструктор с установкой начального значения и конфигурации.
     *
     * Пример использования:
     * $calculator = new CalculatorMathService(100);
     *
     * @param string|float $initialValue Начальная сумма
     * @param CalculatorMathConfigInterface|null $config Конфигурация
     */
    public function __construct(string|float $initialValue = "0", ?CalculatorMathConfigInterface $config = null)
    {
        bcscale(self::DEFAULT_PRECISION);
        $this->summa = $this->sanitizeNumber($initialValue);
        $this->config = $config ?? new CalculatorMathConfig();
    }

    /**
     * Устанавливает сумму.
     *
     * @param float|string $value Новое значение суммы
     * @return void
     */
    public function setSumma(float|string $value): void
    {
        $this->summa = $this->sanitizeNumber($value);
    }

    /**
     *
     */
    public function setCurrentValue(float|string $value): void {
        $this->summa = bcadd($this->sanitizeNumber($value), "0", $this->config->decimalPlaces);
    }

    /**
     * Выполняет арифметический расчет над текущим значением с использованием переданного выражения.
     *
     * Данный метод поддерживает операции сложения (+), вычитания (-), умножения (*), деления (/),
     * а также операции с процентами (%). Если выражение передано без оператора, используется
     * оператор по умолчанию, указанный в конфигурации (`autoSubtractNumbers` и `autoSubtractPercentage`).
     *
     * Возможности и правила использования:
     * - Если выражение начинается с оператора, то применяется именно эта операция:
     *   '+10' — прибавит 10 к текущему значению,
     *   '-5' — отнимет 5 от текущего значения,
     *   '*2' — умножит текущее значение на 2,
     *   '/2' — поделит текущее значение на 2.
     *
     * - Если выражение содержит знак процента (%), то операция вычисляется как процент от текущего значения:
     *   '+10%' — прибавит 10% от текущего значения,
     *   '-20%' — отнимет 20% от текущего значения.
     *
     * - Если выражение передано без оператора, то действие определяется конфигурацией:
     *   Например, при `autoSubtractNumbers = true`, выражение '50' будет вычтено,
     *   а при `autoSubtractNumbers = false` — будет прибавлено.
     *
     * - Можно передавать дополнительные конфигурации во втором параметре `$configOptions`:
     *   [
     *      'autoSubtractNumbers' => (bool) true|false,
     *      'autoSubtractPercentage' => (bool) true|false,
     *      'allowArithmetic' => (bool|string) true|false|'minus',  // разрешены ли арифметические действия
     *      'allowPercentage' => (bool) true|false,                 // разрешены ли проценты
     *      'decimalPlaces' => (int) 2,                             // количество знаков после запятой в результате
     *      'preventNegativeBalance' => (bool) true|false           // предотвращать отрицательный баланс
     *   ]
     *
     * Примеры использования:
     *
     * // Устанавливаем текущую сумму
     * $calculator->setSumma(100);
     *
     * // Простое прибавление
     * $calculator->calculate('+10'); // вернет "110"
     *
     * // Простое вычитание
     * $calculator->calculate('-20'); // вернет "80"
     *
     * // Умножение текущей суммы
     * $calculator->calculate('*2'); // вернет "200"
     *
     * // Деление текущей суммы
     * $calculator->calculate('/4'); // вернет "25"
     *
     * // Прибавление 10% от текущей суммы
     * $calculator->calculate('+10%'); // вернет "110"
     *
     * // Вычитание 15% от текущей суммы
     * $calculator->calculate('-15%'); // вернет "85"
     *
     * // Число без оператора (по умолчанию прибавляется, если autoSubtractNumbers = false)
     * $calculator->calculate('30'); // вернет "130" (100 + 30)
     *
     * // Число без оператора (если autoSubtractNumbers = true)
     * $calculator->calculate('30', ['autoSubtractNumbers' => true]); // вернет "70" (100 - 30)
     *
     * // Запрещено использовать проценты (allowPercentage = false)
     * $calculator->calculate('10%', ['allowPercentage' => false]); // вернет текущую сумму без изменений ("100")
     *
     * // Запрет на отрицательный баланс (preventNegativeBalance = true)
     * $calculator->calculate('-150', ['preventNegativeBalance' => true]); // вернет "0"
     *
     * @param string $expression Арифметическое выражение (например, '10%', '+10', '-5%', '/2', '*4')
     * @param array $configOptions Дополнительные параметры конфигурации (см. выше)
     *
     * @return string Итоговая сумма после вычисления, округленная по настройкам конфигурации.
     */
    public function calculate(string $expression, array $configOptions = []): string
    {
        if (!empty($configOptions)) {
            $this->config->setOptions($configOptions);
        }

        if (is_numeric($expression)) {
            $expression = $this->applyAutoMinusIfNeeded((float)$expression);
        }

        [$operator, $value, $isPercentage] = $this->parseExpression($expression);

        if (!$isPercentage && $this->config->autoSubtractNumbers && !strpbrk($expression, '+-*/')) {
            $operator = '-';
        }

        if ($isPercentage && !strpbrk($expression, '+-*/') && $this->config->autoSubtractPercentage) {
            $operator = '-';
        }

        if (!$this->config->allowPercentage && $isPercentage) {
            return $this->summa;
        }

        if (!$this->isArithmeticAllowed($operator, $isPercentage)) {
            return $this->summa;
        }

        return $this->summa = $this->calculateOperation($this->summa, $operator, $value, $isPercentage);
    }

    /**
     * Добавляет процент к текущему значению (без float).
     *
     * @param string      $percentage decimal-string: "1.5", "-2", "0.25"
     * @param string|null $sign       '+'|'-' или null (если null — берём из значения)
     * @return string
     */
    public function calculateWithPercentage(string $percentage, ?string $sign = null): string
    {
        $percentage = trim($percentage);

        if ($percentage === '' || $percentage === '0' || $percentage === '0.0') {
            return $this->summa;
        }

        // если знак не задан — определяем по самому значению
        if ($sign === null) {
            if ($percentage[0] === '-') {
                $sign = '-';
                $percentage = substr($percentage, 1);
            } else {
                $sign = '+';
                if ($percentage[0] === '+') {
                    $percentage = substr($percentage, 1);
                }
            }
        }

        // защита от "+-1.5" / "--1.5" если вдруг пришло со знаком
        $percentage = ltrim($percentage, '+-');

        if ($percentage === '' || $percentage === '0' || $percentage === '0.0') {
            return $this->summa;
        }

        return $this->calculate($sign . $percentage . '%');
    }

    /**
     * Добавляет или вычитает значение из текущего (без float).
     *
     * @param string      $value decimal-string: "2.5", "-1.25", "0.0001"
     * @param string|null $sign  '+'|'-' или null (если null — берём из значения)
     * @return string
     */
    public function calculateWithValue(string $value, ?string $sign = null): string
    {
        $value = trim($value);

        if ($value === '' || $value === '0' || $value === '0.0') {
            return $this->summa;
        }

        // если знак не задан — определяем по самому значению
        if ($sign === null) {
            if ($value[0] === '-') {
                $sign = '-';
                $value = substr($value, 1);
            } else {
                $sign = '+';
                if ($value[0] === '+') {
                    $value = substr($value, 1);
                }
            }
        }

        // защита от "+-2.5" / "--2.5"
        $value = ltrim($value, '+-');

        if ($value === '' || $value === '0' || $value === '0.0') {
            return $this->summa;
        }

        return $this->calculate($sign . $value);
    }

    /**
     * Выполняет арифметическую операцию с учетом процентов.
     * (Внутренняя функция, используется автоматически.)
     *
     * @param string $base Исходное число
     * @param string $operator Оператор ('+', '-', '*', '/')
     * @param string $value Число для операции
     * @param bool $isPercentage Является ли число процентом
     * @return string Результат операции
     */
    private function calculateOperation(string $base, string $operator, string $value, bool $isPercentage): string
    {
        if ($isPercentage) {
            $value = bcdiv(bcmul($base, $value, self::DEFAULT_PRECISION), "100", self::DEFAULT_PRECISION);
        }

        if ($operator === '/' && (bccomp($value, "0", self::DEFAULT_PRECISION) === 0 || !is_numeric($value))) {
            Log::warning("Попытка деления на ноль в CalculatorMathService");
            return $base;
        }

        $result = match ($operator) {
            '+' => bcadd($base, $value, self::DEFAULT_PRECISION),
            '-' => bcsub($base, $value, self::DEFAULT_PRECISION),
            '*' => bcmul($base, $value, self::DEFAULT_PRECISION),
            '/' => bcdiv($base, $value, self::DEFAULT_PRECISION),
            default => $base
        };

        return ($this->config->preventNegativeBalance && bccomp($result, "0", self::DEFAULT_PRECISION) === -1) ? "0" : $result;
    }

    private function applyAutoMinusIfNeeded(float $value): string
    {
        return ($this->config->autoSubtractNumbers || $this->config->allowArithmetic === false || $this->config->allowArithmetic === 'minus')
            ? "-{$value}" : (string)$value;
    }

    /**
     * Проверяет, разрешены ли арифметические операции согласно конфигурации.
     * (Внутренняя функция, используется автоматически.)
     *
     * @param string $operator оператор
     * @param bool $isPercentage процентный ли это расчёт
     * @return bool разрешено ли выполнение
     */
    private function isArithmeticAllowed(string $operator, bool $isPercentage): bool
    {
        return ($this->config->allowArithmetic === true) ||
            ($this->config->allowArithmetic === 'minus' && ($operator === '-' || $isPercentage));
    }

    /**
     * Парсит переданное арифметическое выражение.
     *
     * @param string $expression Строка выражения (например, '+10%', '-5', '*2', '/2')
     * @return array [string оператор, string значение, bool является ли значение процентом]
     */
    private function parseExpression(string $expression): array
    {
        // Определяем, является ли выражение процентом
        $isPercentage = str_contains($expression, '%');

        // Определяем оператор
        $operator = match (true) {
            str_starts_with($expression, '+') => '+',
            str_starts_with($expression, '-') => '-',
            str_starts_with($expression, '*') => '*',
            str_starts_with($expression, '/') => '/',
            default => '+'
        };

        // Убираем лишние символы, оставляя цифры, проценты и точку
        $cleanedValue = preg_replace('/[^0-9.%]/', '', $expression);

        // Проверка на специальные случаи ("0", "0%", "+0", "-0", "*0", "/0")
        if (preg_match('/^[\+\-\*\/]?0%?$/', $expression)) {
            $operator = '+';
            $numericValue = '0';
        } else {
            // Убираем проценты для численного значения
            $numericValue = str_replace('%', '', $cleanedValue);
        }

        // Проверяем, что очищенное значение числовое и применяем точность
        $value = is_numeric($numericValue)
            ? bcadd($numericValue, "0", self::DEFAULT_PRECISION)
            : "0";

        return [$operator, $value, str_contains($expression, '%')];
    }

    /**
     * Подготавливает число, удаляя лишние символы и приводя его к формату bcmath.
     *
     * @param string|float|null $number Число для обработки
     * @return string Обработанное число (или "0", если входные данные некорректны)
     */
    private function sanitizeNumber(string|float|null $number): string
    {
        if (!is_numeric($number)) {
            //Log::warning("sanitizeNumber: Некорректное значение, установлено '0'", compact('number'));
            return "0";
        }

        // Приводим к строке и удаляем лишние пробелы
        $number = trim((string) $number);

        // Проверяем, является ли число корректным после преобразования
        if (!preg_match('/^-?\d+(\.\d+)?$/', $number)) {
            Log::warning("sanitizeNumber: Ошибка приведения числа, установлено '0'", compact('number'));
            return "0";
        }

        // Безопасное приведение к числу
        return bcadd($number, "0", self::DEFAULT_PRECISION);
    }

    /**
     * Конвертирует очень малые значения для корректного сложения.
     * (Внутренняя функция, используется автоматически.)
     *
     * @param string $base Исходная сумма
     * @param string $value Значение корректировки
     * @return string Скорректированное значение
     */
    private function convertToSmallUnit(string $base, string $value): string
    {
        $basePrecision = strlen(explode('.', $base)[1] ?? '') - strlen(ltrim(explode('.', $base)[1] ?? '', '0'));

        return ($basePrecision > 0)
            ? bcdiv($value, bcpow("10", (string)$basePrecision, self::DEFAULT_PRECISION), self::DEFAULT_PRECISION)
            : $value;
    }


    /**
     * Получает сумму с учетом округления decimalPlaces.
     *
     * @return string Округленное значение суммы
     */
    public function getSumma(): string
    {
        return $this->applyDecimalPrecision($this->summa);
    }

    /**
     * Устанавливает количество знаков после запятой.
     *
     * @param int $decimalPlaces Количество знаков после запятой
     * @return void
     */
    public function setDecimalPlaces(int $decimalPlaces): void
    {
        $this->config->decimalPlaces = max(0, $decimalPlaces); // Гарантируем, что значение не отрицательное
    }

    /**
     * Применяет округление к числу с учетом точности decimalPlaces и удаляет лишние нули.
     *
     * @param float|string $value Число для округления
     * @return string Округленное значение без лишних нулей
     */
    public function applyDecimalPrecision(float|string $value): string
    {
        // Получаем количество знаков после запятой
        $decimalPlaces = is_numeric($this->config->decimalPlaces) ? (int) $this->config->decimalPlaces : 2;

        // Преобразуем в строку и убираем недопустимые символы
        $cleanedValue = preg_replace('/[^0-9.\-]/', '', (string) $value);
        $cleanedValue = ltrim($cleanedValue, '.'); // Убираем точку в начале
        $cleanedValue = rtrim($cleanedValue, '.'); // Убираем точку в конце

        // Проверяем, является ли очищенное значение числом
        if (!is_numeric($cleanedValue)) {
            return '0';
        }

        // Округляем число через bcadd
        $formattedValue = bcadd($cleanedValue, '0', $decimalPlaces);

        // Удаляем лишние нули в конце и точку, если все после неё — нули
        return rtrim(rtrim($formattedValue, '0'), '.');
    }

    /**
     * Сбрасывает сумму к указанному значению (по умолчанию '0').
     *
     * Примеры использования:
     * $calculator->resetSumma(); // Сбросит сумму на '0'
     * $calculator->resetSumma(500); // Сбросит сумму на '500'
     *
     * @param string|float $value Новое начальное значение
     * @return void
     */
    public function resetSumma(string|float $value = '0'): void
    {
        $this->summa = $this->sanitizeNumber($value);
    }

    /**
     * Применяет шаг (корректировку) к текущей сумме.
     *
     * Примеры использования:
     * $calculator->applyStep('+0.001'); // Добавит 0.001 к текущей сумме
     * $calculator->applyStep('-3%'); // Уменьшит сумму на 3%
     * $calculator->applyStep('*2'); // Удвоит текущую сумму
     * $calculator->applyStep('/2'); // Поделит текущую сумму пополам
     *
     * @param string $step Корректировка ('+0.001', '-3%', '*2', '/2')
     * @return string Новая сумма после шага
     */
    public function applyStep(string $step): string
    {
        $step = trim($step);

        if (empty($step)) {
            return $this->summa;
        }

        // Используем существующий функционал parseExpression
        [$operator, $value, $isPercentage] = $this->parseExpression($step);

        // Вызываем уже существующий метод расчета
        return $this->summa = $this->calculateOperation($this->summa, $operator, $value, $isPercentage);
    }

    /**
     * Вычитает указанный процент от текущей суммы.
     *
     * Метод вычисляет процент от текущей суммы и вычитает его.
     * Если включён preventNegativeBalance и итог отрицательный, сумма устанавливается в 0.
     *
     * Пример использования:
     * ```php
     * $calculator->setSumma(200);
     * $calculator->subtractPercentage(10); // итоговая сумма станет 180
     * ```
     *
     * @param float|string $percent Процент, который нужно вычесть.
     * @param int|null $precision Точность вычисления.
     * @return static
     */
    public function subtractPercentage(float|string $percent, ?int $precision = null): static
    {
        $precision = $precision ?? $this->config->decimalPlaces;

        $percent = (string) $this->sanitizeNumber($percent);

        if (bccomp($percent, '0', $precision) <= 0) {
            return $this;
        }

        $summa = $this->sanitizeNumber($this->summa);

        $percentValue = bcmul($summa, bcdiv($percent, '100', $precision), $precision);
        $newSumma = bcsub($summa, $percentValue, $precision);

        if ($this->config->preventNegativeBalance && bccomp($newSumma, '0', $precision) < 0) {
            $newSumma = '0';
        }

        $this->summa = $newSumma;

        return $this;
    }

    /**
     * Вычитает фиксированное значение от текущей суммы.
     *
     * Если включена настройка preventNegativeBalance, итоговая сумма не будет отрицательной.
     *
     * Пример использования:
     * ```php
     * $calculator->setSumma(200);
     * $calculator->subtractFixedValue(50); // итоговая сумма станет 150
     * ```
     *
     * @param float|string $value Значение для вычитания.
     * @param int|null $precision Точность вычисления.
     * @return static
     */
    public function subtractFixedValue(float|string $value, ?int $precision = null): static
    {
        $precision = $precision ?? $this->config->decimalPlaces;

        $value = (string) $this->sanitizeNumber($value);
        if (bccomp($value, '0', $precision) <= 0) {
            return $this;
        }

        $summa = $this->sanitizeNumber($this->summa);
        $newSumma = bcsub($summa, $value, $precision);

        if ($this->config->preventNegativeBalance && bccomp($newSumma, '0', $precision) < 0) {
            $newSumma = '0';
        }

        $this->summa = $newSumma;

        return $this;
    }


    /**
     * Добавляет фиксированную комиссию к текущей сумме.
     *
     * @param float|string $value Значение для добавления.
     * @param int|null $precision Точность вычисления.
     * @return static
     */
    public function addFixedValue(float|string $value, ?int $precision = null): static
    {
        $precision = $precision ?? $this->config->decimalPlaces;

        $value = (string) $this->sanitizeNumber($value);
        if (bccomp($value, '0', $precision) <= 0) {
            return $this;
        }

        $summa = $this->sanitizeNumber($this->summa);
        $newSumma = bcadd($summa, $value, $precision);

        $this->summa = $newSumma;

        return $this;
    }

    /**
     * Добавляет процентную комиссию к текущей сумме.
     *
     * @param float|string $percent Процент, который нужно добавить.
     * @param int|null $precision Точность вычисления.
     * @return static
     */
    public function addPercentage(float|string $percent, ?int $precision = null): static
    {
        $precision = $precision ?? $this->config->decimalPlaces;

        $percent = (string) $this->sanitizeNumber($percent);

        if (bccomp($percent, '0', $precision) <= 0) {
            return $this;
        }

        $summa = $this->sanitizeNumber($this->summa);

        $percentValue = bcmul($summa, bcdiv($percent, '100', $precision), $precision);
        $newSumma = bcadd($summa, $percentValue, $precision);

        $this->summa = $newSumma;

        return $this;
    }
}
