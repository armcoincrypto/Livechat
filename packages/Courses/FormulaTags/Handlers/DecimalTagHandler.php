<?php

declare(strict_types=1);

namespace iEXPackages\Courses\FormulaTags\Handlers;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;

/**
 * DecimalTagHandler
 *
 * Глобально приводит результат выражения внутри тега к заданному количеству знаков после запятой.
 *
 * Синтаксис:
 *   [decimal]N: <выражение>[/decimal]
 *
 * Примеры:
 *   [decimal]8: [cryptocash_usdt-btc] + [cryptocash_usdt-btc][decimal:5][/decimal]
 *   [decimal]2: ([USD - RUB] + 1%) * 10[/decimal]
 *
 * Поведение:
 * - N ограничивается диапазоном 0..$precision (обычно 18)
 * - Сначала вычисляется выражение целиком через FormulaParserService::calculate()
 * - Затем результат приводится к масштабу N с округлением вниз (RoundingMode::DOWN),
 *   чтобы поведение было совместимо с bcmath/bcadd(..., scale) в проекте.
 */
final class DecimalTagHandler implements FormulaTagHandler
{
    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        $expression = trim($expression);
        if ($expression === '') {
            return '0';
        }

        // Ожидаем формат "N: выражение"
        $pos = strpos($expression, ':');
        if ($pos === false) {
            return '0';
        }

        $scaleRaw = trim(substr($expression, 0, $pos));
        $expr = trim(substr($expression, $pos + 1));

        if ($expr === '') {
            return '0';
        }

        // scale ограничиваем 0..precision (обычно 18)
        $scale = (int) $scaleRaw;
        $scale = max(0, min($scale, max(0, $precision)));

        // Считаем выражение целиком
        $value = $parser->calculate($expr, $precision);

        // Любой мусор/пустота => 0
        $value = trim((string) $value);
        if ($value === '' || !is_numeric($value)) {
            return '0';
        }

        // Приведение к масштабу через Brick (точно, без float)
        try {
            $decimal = BigDecimal::of($value)->toScale($scale, RoundingMode::DOWN);
            $out = $decimal->__toString();
        } catch (MathException) {
            return '0';
        } catch (\Throwable) {
            return '0';
        }

        // Убираем хвостовые нули для компактности
        if (str_contains($out, '.')) {
            $out = rtrim($out, '0');
            $out = rtrim($out, '.');
        }

        // Нормализуем "-0" / "-0.0" => "0"
        if ($out === '-0' || preg_match('/^-?0(?:\.0+)?$/', $out)) {
            return '0';
        }

        return $out === '' ? '0' : $out;
    }
}
