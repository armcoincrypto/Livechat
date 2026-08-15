<?php
declare(strict_types=1);

namespace iEXPackages\Calculator\Traits;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Log;

/**
 * Трейт InteractsWithNumbers предоставляет набор методов для безопасного и точного взаимодействия с числами,
 * включая обработку и валидацию числовых данных, математических выражений и финансовых значений.
 *
 * Основное назначение:
 * - Обеспечение высокой точности числовых расчётов, включая поддержку криптовалютных и традиционных валютных операций.
 * - Валидация и очистка данных от некорректных значений.
 * - Работа с математическими выражениями и форматирование чисел для удобного отображения.
 *
 * Основные методы:
 *
 * - sanitizeNumber:
 *   Безопасно очищает число, приводя его к формату с заданной точностью, поддерживая обычные и криптовалютные значения.
 *
 * - isValidNumber:
 *   Проверяет, является ли входящее значение корректным числом.
 *
 * - isGreaterThanZero:
 *   Проверяет, что число больше нуля с высокой точностью (поддерживает числа с точностью до 18 знаков после запятой).
 *
 * - compareValues:
 *   Сравнивает два числа между собой с заданной точностью, поддерживая как обычные, так и криптовалютные значения.
 *
 * - isWithinCourseLimits:
 *   Проверяет, находится ли указанное число в пределах заданного диапазона (минимум и максимум).
 *
 * - sanitizeMathExpression:
 *   Очищает математическое выражение, удаляя невалидные операции (например, деление или умножение на 0).
 *
 * - isValidMathExpression:
 *   Валидирует математические выражения, проверяя наличие допустимых символов и операторов.
 *
 * - safeNumberFormat:
 *   Форматирует числа с высокой точностью и добавлением разделителей для удобного отображения.
 *
 * - adjustNumberFormat
 *   Корректирует количество знаков после запятой до максимально допустимого.
 *
 * Этот трейт рекомендуется использовать в финансовых и криптовалютных приложениях для предотвращения ошибок,
 * связанных с неточностями или некорректными значениями при выполнении числовых операций.
 */
trait InteractsWithNumbers
{
    /**
     * Проверяет, находится ли сумма в пределах допустимого курса.
     *
     * Учитывает нижний и верхний пределы курса (минимум и максимум).
     * Если минимум или максимум равны нулю, проверка на это значение игнорируется.
     *
     * @param string|float $amount Проверяемая сумма
     * @param string|float $min Минимальный предел (0 — нет ограничения)
     * @param string|float $max Максимальный предел (0 — нет ограничения)
     *
     * @return bool True, если сумма в рамках указанных пределов, иначе False.
     */
    public function isWithinCourseLimits(string|float $amount, string|float $min, string|float $max): bool
    {
        if (!$this->isValidNumber($amount) || !$this->isValidNumber($min) || !$this->isValidNumber($max)) {
            Log::error("isWithinCourseLimits: Некорректные значения", compact('amount', 'min', 'max'));
            return false;
        }

        try {
            $minCheck = $this->compareValues($min, '0') === 0 || $this->compareValues($amount, $min) >= 0;
            $maxCheck = $this->compareValues($max, '0') === 0 || $this->compareValues($amount, $max) <= 0;

            return $minCheck && $maxCheck;
        } catch (\Throwable $e) {
            Log::error("Ошибка при проверке лимитов курса: {$e->getMessage()}", compact('amount', 'min', 'max'));
            return false;
        }
    }

    /**
     * Сравнивает два числовых значения с заданной точностью.
     *
     * Поддерживает обычные числа и высокоточную проверку чисел для криптовалют.
     *
     * @param string|float $a Первое число для сравнения
     * @param string|float $b Второе число для сравнения
     * @param int $scale Точность сравнения (по умолчанию 8 знаков после запятой)
     *
     * @return int 0 если равны, 1 если первое больше второго, -1 если первое меньше второго.
     *             При невалидных аргументах возвращает 0 и записывает ошибку в лог.
     */
    private function compareValues(string|float $a, string|float $b, int $scale = 18): int
    {
        if (!$this->isValidNumber($a) || !$this->isValidNumber($b)) {
            Log::error("compareValues: Некорректные аргументы", compact('a', 'b'));
            return 0;
        }

        try {
            $left  = BigDecimal::of((string) $a)->toScale($scale, RoundingMode::DOWN);
            $right = BigDecimal::of((string) $b)->toScale($scale, RoundingMode::DOWN);

            return $left->compareTo($right);
        } catch (\Throwable $e) {
            Log::error("compareValues: Ошибка сравнения через BigDecimal: {$e->getMessage()}", compact('a', 'b'));
            return 0;
        }
    }

    /**
     * Проверяет значение на числовую валидность.
     *
     * @param mixed $value Значение для проверки
     * @return bool True, если значение является числом или числовой строкой
     */
    protected function isValidNumber(mixed $value): bool
    {
        return is_numeric($value);
    }

    /**
     * Проверяет, является ли переданное число больше нуля.
     *
     * Поддерживает как обычные числа, так и высокоточную проверку криптовалютных значений.
     * Использует BCMath для корректного сравнения с точностью до 18 знаков после запятой.
     *
     * @param float|string $value Число для проверки (строка или число с плавающей точкой)
     *
     * @return bool True, если число больше нуля, иначе false (включая случаи некорректного значения)
     */
    protected function isGreaterThanZero(float|string $value): bool
    {
        $v = $this->sanitizeNumber($value, 18);
        return $this->compareValues($v, '0', 18) === 1;
    }

    /**
     * Безопасно очищает число и возвращает десятичную строку (без потери точности).
     *
     * - Понимает scientific notation (1e-5)
     * - Убирает пробелы/NBSP, запятые → точки
     * - Возвращает "0" для мусора/NaN/Inf/∞
     * - Масштаб: до 18 знаков (можно параметризовать)
     */
    protected function sanitizeNumber(mixed $value, int $scale = 18): string
    {
        // Быстрый отсев
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return '0';
        }

        // Полная нормализация через Brick (у тебя уже есть)
        return $this->toBcString($value, $scale);
    }

    /**
     * Мягкая очистка выражения:
     * - нормализует запятые/пробелы
     * - оставляет только цифры и + - * / . %
     * - запрещает деление на точный ноль (/0, /0.0, /00.000)
     * - убирает нейтральные +0/-0 и +0%/-0% (но НЕ трогает +0.1, -0.25, *0.5)
     */
    protected function sanitizeMathExpression(mixed $expression): string
    {
        if (!is_string($expression) || trim($expression) === '') {
            return '0';
        }

        $expr = trim($expression);

        // 1) Нормализация локали и пробелов
        $expr = str_replace(',', '.', $expr);
        $expr = preg_replace('/\s+/', '', $expr);

        // 2) Оставляем только допустимые символы
        $expr = preg_replace('/[^0-9+\-*\/.%]/', '', $expr);

        if ($expr === '') {
            return '0';
        }

        // 3) Запрет деления на точный ноль: /0, /0.0, /00.000
        //    Важно: /0.5 НЕ трогаем
        $expr = preg_replace('/\/0+(?:\.0+)?(?![\d.])/', '', $expr);

        // 4) Убираем нейтральные "+0", "-0" (строго ноль, без дробной части)
        //    "+0.1" и "-0.1" не трогаем
        $expr = preg_replace('/([+\-])0+(?![\d.])/', '', $expr);

        // 5) Убираем нейтральные "+0%", "-0%" и "+0.0%", "-0.0%"
        $expr = preg_replace('/([+\-])0+(?:\.0+)?%(?![\d.])/', '', $expr);

        // 6) Убираем хвостовые операторы (кроме %)
        $expr = rtrim($expr, "+-*/");

        return $expr !== '' ? $expr : '0';
    }

    /**
     * Строгая очистка выражения:
     * - запятые -> точки
     * - убирает пробелы
     * - оставляет только допустимые символы (с % или без)
     * - НЕ ломает *0.5, /0.25, +0.1
     * - запрещает деление на точный ноль (/0, /0.0, /00.000)
     * - аккуратно чистит повторы операторов, не уничтожая смысл "--" в середине числа
     */
    protected function sanitizeFlexibleMathExpression(mixed $expression, bool $allowPercent = true): string
    {
        if (!is_string($expression) || trim($expression) === '') {
            return '0';
        }

        $expr = trim($expression);

        // 1) Нормализация
        $expr = str_replace(',', '.', $expr);
        $expr = preg_replace('/\s+/', '', $expr);

        // 2) Оставляем только допустимые символы
        if ($allowPercent) {
            $expr = preg_replace('/[^0-9+\-*\/.%]/', '', $expr);
        } else {
            $expr = preg_replace('/[^0-9+\-*\/.]/', '', $expr);
            $expr = str_replace('%', '', $expr);
        }

        if ($expr === '') {
            return '0';
        }

        // 3) Убираем повторяющиеся точки
        $expr = preg_replace('/\.{2,}/', '.', $expr);

        // 4) Запрет деления на точный ноль (/0, /0.0, /00.000)
        $expr = preg_replace('/\/0+(?:\.0+)?(?![\d.])/', '', $expr);

        // 5) Убираем нейтральные "+0", "-0" (строго ноль)
        $expr = preg_replace('/([+\-])0+(?![\d.])/', '', $expr);

        // 6) Убираем нейтральные "+0%", "-0%" и "+0.0%", "-0.0%"
        if ($allowPercent) {
            $expr = preg_replace('/([+\-])0+(?:\.0+)?%(?![\d.])/', '', $expr);
        }

        // 7) Лёгкая нормализация операторов:
        //    - схлопываем только "++", "**", "//", "%%" и т.п. в один оператор
        //    - НЕ трогаем "--" и "+-" / "-+" (чтобы не ломать отрицательные числа в середине)
        $expr = preg_replace('/(\+{2,})/', '+', $expr);
        $expr = preg_replace('/(\*{2,})/', '*', $expr);
        $expr = preg_replace('/(\/{2,})/', '/', $expr);
        if ($allowPercent) {
            $expr = preg_replace('/(%{2,})/', '%', $expr);
        }

        // 8) Убираем хвостовые операторы (кроме %)
        $expr = rtrim($expr, '+-*/');

        return $expr !== '' ? $expr : '0';
    }

    /*
    * Валидирует математическое выражение с учетом гибкой логики (замена запятых на точки, удаление пробелов и т.д.).
    *
    * @param mixed $expression
    * @param bool $allowPercent
    * @return bool
    */
    protected function isValidFlexibleMathExpression(mixed $expression, bool $allowPercent = true): bool
    {
        if (!is_string($expression) || trim($expression) === '') {
            return false;
        }

        $expression = str_replace(',', '.', trim($expression));
        $expression = preg_replace('/\s*([+\-*\/%])\s*/', '$1', $expression);

        $allowedChars = $allowPercent ? '0-9+\-*\/.%' : '0-9+\-*\/.';

        // только допустимые символы
        if (!preg_match('/^[' . $allowedChars . ']+$/', $expression)) {
            return false;
        }

        // не допускаем подряд идущие операторы (кроме случаев "+-" и "--" и т.п. мы тут упрощаем — ок)
        if (preg_match('/([+\-*\/%]){2,}/', $expression)) {
            return false;
        }

        // запрещаем ТОЛЬКО деление на точный ноль: /0, /0.0, /00.000
        // /0.5 и /0.25 — разрешены
        if (preg_match('/\/0+(?:\.0+)?(?![\d.])/', $expression)) {
            return false;
        }

        return true;
    }

    /**
     * Валидирует математическое выражение.
     *
     * @param mixed $expression Выражение для проверки
     * @param bool $allowPercent Разрешены ли проценты (%)
     * @return bool
     */
    protected function isValidMathExpression(mixed $expression, bool $allowPercent = true): bool
    {
        if (!is_string($expression) || trim($expression) === '') {
            return false;
        }

        $allowedChars = $allowPercent ? '0-9+\-*\/.%\s' : '0-9+\-*\/.\s';

        if (!preg_match("/^[$allowedChars]+$/", $expression)) {
            return false;
        }

        $compact = str_replace(' ', '', $expression);

        if (preg_match('/[+\-*\/.%]{2,}/', $compact)) {
            return false;
        }

        // запрещаем ТОЛЬКО деление на точный ноль
        if (preg_match('/\/\s*0+(?:\.0+)?(?![\d.])/', $compact)) {
            return false;
        }

        return true;
    }

    private function isValidProfitBareValue(?string $v): bool
    {
        if (!is_string($v)) return false;
        $s = trim($v);
        if ($s === '' || $s[0] === '+' || $s[0] === '-') return false;
        return (bool) preg_match('/^\d+(?:[.,]\d+)?%?$/u', $s);
    }

    /**
     * Безопасное форматирование чисел с высокой точностью (например, для криптовалют).
     *
     * @param float|string $number Число для форматирования
     * @param int $decimals Количество знаков после запятой
     * @param bool $withSpaces Разделять тысячи пробелами
     * @return string
     */
    private function safeNumberFormat(float|string $number, int $decimals, bool $withSpaces = false): string
    {
        if (!is_numeric($number) || $this->compareValues((string)$number, '0', $decimals) <= 0) {
            return '0';
        }

        // Нормализуем число в строку с нужным scale
        $number = $this->toBcString($number, $decimals);

        // Разделяем на целую и дробную части
        [$intPart, $fracPart] = array_pad(explode('.', $number), 2, '');

        // Добавляем разделитель тысяч, если требуется
        if ($withSpaces) {
            $intPart = preg_replace('/\B(?=(\d{3})+(?!\d))/', ' ', $intPart);
        }

        // Убираем лишние нули из дробной части
        if ($fracPart !== '') {
            $fracPart = rtrim($fracPart, '0');
            $formattedNumber = $fracPart !== ''
                ? $intPart . '.' . $fracPart
                : $intPart;
        } else {
            $formattedNumber = $intPart;
        }

        return $formattedNumber === '' ? '0' : $formattedNumber;
    }

    /**
     * Корректирует количество знаков после запятой до максимально допустимого.
     *
     * Используется для безопасного форматирования чисел, особенно при работе с криптовалютами.
     *
     * @param int $numberFormat Исходное количество знаков после запятой.
     * @param int $maxNumberFormat Максимально допустимое количество знаков после запятой.
     * @return int Итоговое количество знаков после запятой, не превышающее допустимое.
     */
    private function adjustNumberFormat(int $numberFormat, int $maxNumberFormat): int
    {
        return min($numberFormat, $maxNumberFormat);
    }

    private function isValidStandardNumber(mixed $value): bool
    {
        if (!is_numeric($value)) {
            return false;
        }

        // Разрешены стандартные числа с точностью до 8 знаков
        if (preg_match('/^\d+(\.\d{1,8})?$/', (string) $value)) {
            return true;
        }

        return false;
    }

    private function isValidCryptoNumber(mixed $value): bool
    {
        if (!is_numeric($value)) {
            return false;
        }

        // Разрешены числа и значения с высокой точностью (crypto)
        if (preg_match('/^\d+(\.\d{1,36})?$/', (string) $value)) {
            return true;
        }

        return false;
    }

    /**
     * Нормализует числовое значение в десятичную строку без потери точности.
     *
     * Поддерживает:
     * - обычные числа
     * - scientific notation (1e-5, 2.3E+7)
     * - строки с пробелами и запятыми
     *
     * НЕ использует float.
     * Безопасна для крипты и финансовых расчётов.
     */
    protected function normalizeNumber(float|string $value, int $precision = 18): string
    {
        try {
            return $this->toBcString($value, $precision);
        } catch (\Throwable $e) {
            Log::warning('normalizeNumber: ошибка нормализации', [
                'value' => $value,
                'precision' => $precision,
                'error' => $e->getMessage(),
            ]);
            return '0';
        }
    }


    /**
     * Конвертирует произвольное числовое значение в десятичную строку, совместимую с BCMath (без хвостовых нулей).
     *
     * Цель: безопасная нормализация чисел перед использованием в `bcadd`, `bcsub`, `bccomp` и др.,
     * включая поддержку научной нотации (1.23E-5), локальных форматов (запятая как разделитель),
     * пробелов/неразрывных пробелов и ведущего знака `+`. Для точного парсинга используется
     * `Brick\Math\BigDecimal`, округление выполняется `RoundingMode::DOWN` (в сторону нуля).
     * Результат дополнительно очищается от незначащих нулей в дробной части и нормализуется `-0` → `0`.
     *
     * Основные гарантии:
     * - Понимает `1e-5`, `-2.3E+7`, обычные десятичные строки и числа.
     * - Убирает все виды пробельных символов (включая NBSP `\u{00A0}`) и заменяет запятую на точку.
     * - Масштаб приводится к неотрицательному (`$scale < 0` → `0`), округление `DOWN`.
     * - Итоговая строка **не содержит хвостовых нулей**, кроме необходимого нуля до точки.
     * - Пустые/нечисловые значения, `NaN`, `Inf`, `Infinity`, `∞` → возвращается строка `"0"`.
     *
     * Примеры:
     * ```php
     * // Базовые
     * $this->toBcString('123.4500', 8);         // "123.45"
     * $this->toBcString('123,4500', 8);         // "123.45"
     * $this->toBcString('+0.5000', 8);          // "0.5"
     *
     * // Научная нотация
     * $this->toBcString('1.13493E-5', 18);      // "0.0000113493"
     * $this->toBcString('-2e+3', 6);            // "-2000"
     *
     * // Пробелы, NBSP
     * $this->toBcString('1\u{00A0}234,5600', 8); // "1234.56"
     * $this->toBcString('  9 876 543,210000 ', 8); // "9876543.21"
     *
     * // Плохие значения
     * $this->toBcString('', 8);                 // "0"
     * $this->toBcString('NaN', 8);              // "0"
     * $this->toBcString('∞', 8);                // "0"
     * $this->toBcString('-0.0000', 8);          // "0"
     * ```
     *
     * Рекомендации:
     * - Перед сравнением через `bccomp($a, $b, $scale)` нормализуйте оба аргумента этим методом.
     * - Для сумм/разниц через `bcadd/bcsub` нормализуйте исходные значения и выбирайте единый `$scale` для домена.
     *
     * @param mixed $value Число или числовая строка (`int|float|string`), допускается scientific notation
     * @param int   $scale Целевой масштаб (кол-во дробных знаков), нормализуется к `>= 0` (по умолчанию 18)
     * @return string Десятичная строка без хвостовых нулей и без «-0»; при невалидном вводе — "0".
     */
    protected function toBcString(mixed $value, int $scale = 18): string
    {
        // Нормализуем scale
        $scale = max(0, (int) $scale);

        // Быстрые отсеки
        $raw = (string) $value;
        $raw = trim($raw);

        // Пусто/NaN/Inf/Infinity/∞ -> "0"
        if ($raw === '' ||
            strcasecmp($raw, 'nan') === 0 ||
            strcasecmp($raw, 'inf') === 0 ||
            strcasecmp($raw, 'infinity') === 0 ||
            $raw === '∞'
        ) {
            return '0';
        }

        try {
            // Заменяем запятую на точку и вычищаем ВСЕ пробелы (включая неразрывные)
            $normalized = str_replace(',', '.', $raw);
            $normalized = preg_replace('/[\x{00A0}\s]+/u', '', $normalized); // убрать NBSP и любые пробелы/табуляции/переводы строк

            // Явно допускаем ведущий плюс
            if ($normalized !== '' && $normalized[0] === '+') {
                $normalized = substr($normalized, 1);
            }

            // Создаём BigDecimal (понимает 1e-5, 1.23E+7, обычные десятичные)
            $decimal = BigDecimal::of($normalized)->toScale($scale, RoundingMode::DOWN);

            $out = $decimal->__toString();

            // Убираем хвостовые нули в дробной части: "0.000011349300000000" -> "0.0000113493"
            if (str_contains($out, '.')) {
                $out = rtrim($out, '0');
                $out = rtrim($out, '.');
            }

            // Нормализуем -0 / -0.0... -> 0
            if ($out === '-0' || preg_match('/^-?0(?:\.0+)?$/', $out)) {
                return '0';
            }

            return $out;
        } catch (\Throwable $e) {
            // Любая ошибка парсинга -> "0"
            if (class_exists('\\Log')) {
                \Log::warning('toBcString: ошибка преобразования', [
                    'value' => $value,
                    'error' => $e->getMessage(),
                ]);
            }
            return '0';
        }
    }

    /**
     * Сложение двух чисел в строковом виде с точностью $scale.
     * Использует BCMath если доступен, иначе Brick\Math (у тебя уже подключён).
     */
    protected function mathAdd(string|float|int $a, string|float|int $b, int $scale = 18): string
    {
        $scale = max(0, (int) $scale);
        $left  = $this->toBcString($a, $scale);
        $right = $this->toBcString($b, $scale);

        if (function_exists('bcadd')) {
            return (string) bcadd($left, $right, $scale);
        }

        // Brick fallback
        return BigDecimal::of($left)->plus(BigDecimal::of($right))
            ->toScale($scale, RoundingMode::DOWN)
            ->__toString();
    }

    /**
     * Вычитание двух чисел в строковом виде с точностью $scale.
     */
    protected function mathSub(string|float|int $a, string|float|int $b, int $scale = 18): string
    {
        $scale = max(0, (int) $scale);
        $left  = $this->toBcString($a, $scale);
        $right = $this->toBcString($b, $scale);

        if (function_exists('bcsub')) {
            return (string) bcsub($left, $right, $scale);
        }

        // Brick fallback
        return BigDecimal::of($left)->minus(BigDecimal::of($right))
            ->toScale($scale, RoundingMode::DOWN)
            ->__toString();
    }

    /**
     * Сравнение на равенство с точностью $scale.
     */
    protected function mathEquals(string|float|int $a, string|float|int $b, int $scale = 18): bool
    {
        $scale = max(0, (int) $scale);
        $left  = $this->toBcString($a, $scale);
        $right = $this->toBcString($b, $scale);

        if (function_exists('bccomp')) {
            return bccomp($left, $right, $scale) === 0;
        }

        return BigDecimal::of($left)->compareTo(BigDecimal::of($right)) === 0;
    }
}
