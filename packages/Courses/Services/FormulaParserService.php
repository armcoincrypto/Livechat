<?php

namespace iEXPackages\Courses\Services;

use iEXPackages\Calculator\Traits\InteractsWithNumbers;
use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use Illuminate\Support\Facades\Log;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * Сервис для разбора и вычисления математических формул.
 *
 * Особенности:
 * - Высокоточная арифметика через BCMath (до 18 знаков и более)
 * - Поддержка пользовательских тегов
 * - Поддержка одиночных тегов вида [tag:value]
 * - Поддержка процентных вычислений (5% → 0.05)
 * - ExpressionLanguage инициализируется один раз на процесс
 * - Кеширование вычисленных шаблонов
 */
class FormulaParserService
{
    use InteractsWithNumbers;

    /**
     * Исходные данные формулы.
     *
     * @var array<string, string>
     */
    private array $data;

    /**
     * Шаблоны формул.
     *
     * @var array<string, string>
     */
    private array $templates;

    /**
     * Разрешена ли обработка одиночных тегов.
     */
    private bool $allowSingleTags = false;

    /**
     * Пользовательские переменные.
     *
     * @var array<string, string>
     */
    private array $variables = [];

    /**
     * Зарегистрированные обработчики тегов.
     *
     * @var array<string, FormulaTagHandler>
     */
    private array $tagHandlers = [];

    /**
     * Подготовленные float-переменные для ExpressionLanguage.
     *
     * @var array<string, float>
     */
    private array $floatVars = [];

    /**
     * Кеш вычисленных шаблонов.
     *
     * @var array<string, string>
     */
    private array $templateCache = [];

    /**
     * Глобальный экземпляр ExpressionLanguage.
     */
    private static ?ExpressionLanguage $language = null;

    /**
     * @param array<string, string> $data
     * @param array<string, string> $templates
     */
    public function __construct(array $data, array $templates = [])
    {
        $this->data = $this->sanitizeData($data);
        $this->templates = $templates;

        foreach ($this->data as $key => $value) {
            $cleanKey = trim((string)$key, '[]');
            $this->floatVars[$cleanKey] = (float)$value;
        }

        self::bootExpressionLanguage();
    }

    /**
     * Инициализация ExpressionLanguage один раз на процесс.
     */
    private static function bootExpressionLanguage(): void
    {
        if (self::$language instanceof ExpressionLanguage) {
            return;
        }

        $language = new ExpressionLanguage();

        $language->register(
            'avg',
            fn (...$values) => sprintf('(array_sum([%s]) / %d)', implode(',', $values), count($values)),
            fn ($arguments, ...$values) => array_sum($values) / max(1, count($values))
        );

        $language->register(
            'median',
            fn (...$values) => sprintf('median([%s])', implode(',', $values)),
            function ($arguments, ...$values) {
                sort($values);
                $count = count($values);
                $middle = intdiv($count, 2);
                return $count % 2
                    ? $values[$middle]
                    : ($values[$middle - 1] + $values[$middle]) / 2;
            }
        );

        $language->register(
            'min',
            fn (...$values) => sprintf('min([%s])', implode(',', $values)),
            fn ($arguments, ...$values) => min($values)
        );

        $language->register(
            'max',
            fn (...$values) => sprintf('max([%s])', implode(',', $values)),
            fn ($arguments, ...$values) => max($values)
        );

        $language->register(
            'round',
            fn ($value, $precision) => sprintf('round(%s, %d)', $value, $precision),
            fn ($arguments, $value, $precision) => round($value, (int)$precision)
        );

        $language->register(
            'abs',
            fn ($value) => sprintf('abs(%s)', $value),
            fn ($arguments, $value) => abs($value)
        );

        $language->register(
            'sum',
            fn (...$values) => sprintf('array_sum([%s])', implode(',', $values)),
            fn ($arguments, ...$values) => array_sum($values)
        );

        $language->register(
            'if',
            fn ($condition, $trueValue, $falseValue) => sprintf('(%s ? %s : %s)', $condition, $trueValue, $falseValue),
            fn ($arguments, $condition, $trueValue, $falseValue) => $condition ? $trueValue : $falseValue
        );

        self::$language = $language;
    }

    /**
     * Разрешает обработку одиночных тегов.
     */
    public function allowSingleTags(bool $allow = true): void
    {
        $this->allowSingleTags = $allow;
    }

    /**
     * Регистрирует обработчик тега.
     */
    public function registerTagHandler(string $tag, FormulaTagHandler $handler): void
    {
        $this->tagHandlers[$tag] = $handler;
    }

    /**
     * Устанавливает пользовательскую переменную.
     */
    public function setVariable(string $key, string $value): void
    {
        $this->variables[$key] = $value;
    }

    /**
     * Получает пользовательскую переменную.
     */
    public function getVariable(string $key): ?string
    {
        return $this->variables[$key] ?? null;
    }

    /**
     * Основной метод вычисления выражения.
     */
    public function calculate(string $expression, int $precision = 18): string
    {
        $precision = max(0, min($precision, 18));
        bcscale($precision);

        // 1) Парные теги: [tag]...[/tag]
        $expression = preg_replace_callback(
            '/\[(\w[\w-]*)\](.*?)\[\/\1\]/s',
            fn (array $m): string => $this->dispatchTag((string) $m[1], (string) $m[2], $precision),
            $expression
        );

        // 2) Одиночные теги: [tag:value]
        if ($this->allowSingleTags) {
            $expression = preg_replace_callback(
                '/\[(\w+):((?:[^\[\]]++|\[(?2)\])++)\]/',
                fn (array $m): string => $this->dispatchTag((string) $m[1], trim((string) $m[2]), $precision),
                $expression
            );
        }

        // 3) Модификаторы точности: [CODE][decimal:N]
        //    Делаем до общей подстановки [CODE], чтобы сохранить смысл модификатора.
        $expression = $this->applyDecimalModifiers($expression, $precision);

        // 4) Подстановка плейсхолдеров: [CODE]
        $evaluated = preg_replace_callback('/\[(.*?)\]/', function (array $m) use ($precision): string {
            $raw = trim((string) $m[1]);

            if ($raw === '') {
                return '0';
            }

            if (isset($this->variables[$raw])) {
                return (string) $this->variables[$raw];
            }

            $key = '[' . $raw . ']';

            if (isset($this->data[$key])) {
                return (string) $this->data[$key];
            }

            if (isset($this->templates[$key])) {
                if (!isset($this->templateCache[$key])) {
                    $this->templateCache[$key] = $this->calculate((string) $this->templates[$key], $precision);
                }

                return (string) $this->templateCache[$key];
            }

            return '0';
        }, $expression);

        $evaluated = trim((string) $evaluated);
        if ($evaluated === '') {
            return '0';
        }

        // 5) Проценты в виде "100 + 5%" / "100 - 5%"
        $evaluated = preg_replace_callback(
            '/(\d+(?:\.\d+)?)\s*([+-])\s*(\d+(?:\.\d+)?)%/',
            function (array $m) use ($precision): string {
                $base = (string) $m[1];
                $op = (string) $m[2];
                $percent = (string) $m[3];

                $delta = bcmul($base, bcdiv($percent, '100', $precision), $precision);

                return $op === '+'
                    ? bcadd($base, $delta, $precision)
                    : bcsub($base, $delta, $precision);
            },
            $evaluated
        );

        // 6) Процент как число: "5%" => 0.05
        $evaluated = preg_replace_callback(
            '/(\d+(?:\.\d+)?)%/',
            fn (array $m): string => bcdiv((string) $m[1], '100', $precision),
            $evaluated
        );

        // 7) Если получилось число — возвращаем как bc-строку
        if ($this->isValidNumber($evaluated)) {
            return bcadd($evaluated, '0', $precision);
        }

        // 8) Иначе — пытаемся вычислить арифметику
        if (preg_match('/[+\-*\/()]/', $evaluated)) {
            return $this->evaluateExpression($evaluated, $precision);
        }

        return '0';
    }

    /**
     * Модификатор точности: [CODE][decimal:N]
     *
     * Важно:
     * - Это применяется ДО общей подстановки [CODE]
     * - N ограничивается 0..$precision
     * - Используется bcadd(.., N) (отрезание до N знаков по scale)
     */
    private function applyDecimalModifiers(string $expression, int $precision): string
    {
        return (string) preg_replace_callback(
            '/\[(.*?)\]\[decimal:(\d+)\]/i',
            function (array $m) use ($precision): string {
                $token = trim((string) $m[1]);
                $decimals = (int) $m[2];
                $decimals = max(0, min($decimals, $precision));

                if ($token === '') {
                    return '0';
                }

                $value = $this->resolveBracketToken($token, $precision);

                if ($value === '' || !is_numeric($value)) {
                    return '0';
                }

                $rounded = bcadd((string) $value, '0', $decimals);
                $rounded = rtrim(rtrim($rounded, '0'), '.');

                return $rounded === '' ? '0' : $rounded;
            },
            $expression
        );
    }

    /**
     * Возвращает значение плейсхолдера без внешних квадратных скобок.
     * Поведение совпадает с обычной подстановкой [CODE] в calculate().
     */
    private function resolveBracketToken(string $token, int $precision): string
    {
        $token = trim($token);
        if ($token === '') {
            return '0';
        }

        if (isset($this->variables[$token])) {
            return (string) $this->variables[$token];
        }

        $key = '[' . $token . ']';

        if (isset($this->data[$key])) {
            return (string) $this->data[$key];
        }

        if (isset($this->templates[$key])) {
            if (!isset($this->templateCache[$key])) {
                $this->templateCache[$key] = $this->calculate((string) $this->templates[$key], $precision);
            }

            return (string) $this->templateCache[$key];
        }

        return '0';
    }

    /**
     * Выполняет вычисление через ExpressionLanguage.
     */
    private function evaluateExpression(string $expression, int $precision): string
    {
        try {
            $result = self::$language->evaluate($expression, $this->floatVars);

            if (!is_numeric($result)) {
                Log::warning('Некорректный результат вычисления', compact('expression', 'result'));
                return '0';
            }

            return number_format((float)$result, $precision, '.', '');
        } catch (\Throwable $e) {
            Log::error('Ошибка вычисления выражения', [
                'expression' => $expression,
                'error' => $e->getMessage(),
            ]);
            return '0';
        }
    }

    /**
     * Делегирует обработку зарегистрированному обработчику тега.
     */
    private function dispatchTag(string $tag, string $content, int $precision): string
    {
        if (!isset($this->tagHandlers[$tag])) {
            return '0';
        }

        return $this->tagHandlers[$tag]->handle($this, $content, $precision);
    }

    /**
     * Фильтрует входные данные.
     *
     * @param array<string, string> $data
     * @return array<string, string>
     */
    private function sanitizeData(array $data): array
    {
        return array_filter(
            $data,
            fn ($value) =>
                $this->isValidStandardNumber($value) ||
                $this->isValidCryptoNumber($value)
        );
    }
}
