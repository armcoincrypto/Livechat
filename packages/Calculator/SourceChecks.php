<?php

declare(strict_types=1);

namespace iEXPackages\Calculator;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use iEXPackages\Calculator\Strategies\IndependentRubStrategy;
use iEXPackages\Calculator\Traits\InteractsWithNumbers;

/**
 * SourceChecks
 *
 * Набор проверок доступности (активности) источников курса для направления обмена.
 *
 * ВАЖНО:
 * - Методы возвращают `true`, если источник можно использовать для расчёта курса.
 * - Проверки быстрые: не выполняют вычислений курса, а только валидируют наличие привязки и статус.
 * - Для ручного курса дополнительно проверяется, что значение является корректным числом и строго больше 0.
 */
final class SourceChecks
{
    use InteractsWithNumbers;

    /**
     * RUB directions require a fresh, approved, non-circular independent source.
     */
    public static function independentRub(object $dir): bool
    {
        if (!$dir instanceof \App\Models\DirectionExchange) {
            return false;
        }

        $rate = (new IndependentRubStrategy($dir))->getRate();

        return is_numeric($rate) && bccomp($rate, '0', 18) === 1;
    }

    public static function independentRubName(object $dir): string
    {
        return 'Independent RUB';
    }

    /**
     * Проверяет, активен ли источник BestChange для направления.
     *
     * Условия:
     * - есть связанная запись bestchange_directions
     * - статус связи = 1
     *
     * @param object $dir Объект направления (ожидается свойство bestchange_directions).
     * @return bool true — источник доступен, false — недоступен.
     */
    public static function bestchange(object $dir): bool
    {
        return isset($dir->bestchange_directions)
            && (int) ($dir->bestchange_directions->status ?? 0) === 1;
    }

    /**
     * Проверяет, активен ли источник "Курсы из файла" для направления.
     *
     * Условия:
     * - указан id_file_parser_rate (id > 0 / не пусто)
     * - связанная сущность file_parser_rates имеет статус = 1
     *
     * @param object $dir Объект направления (ожидаются id_file_parser_rate и file_parser_rates).
     * @return bool true — источник доступен, false — недоступен.
     */
    public static function file(object $dir): bool
    {
        $id = (string) ($dir->id_file_parser_rate ?? '');
        if ($id === '' || $id === '0') {
            return false;
        }

        return (int) (optional($dir->file_parser_rates)->status ?? 0) === 1;
    }

    /**
     * Проверяет, активен ли источник "Конкуренты" для направления.
     *
     * Условия:
     * - указан id_competitor (id > 0 / не пусто)
     * - связанная сущность competitor_rates имеет статус = 1
     *
     * @param object $dir Объект направления (ожидаются id_competitor и competitor_rates).
     * @return bool true — источник доступен, false — недоступен.
     */
    public static function competitor(object $dir): bool
    {
        $id = (string) ($dir->id_competitor ?? '');
        if ($id === '' || $id === '0') {
            return false;
        }

        return (int) (optional($dir->competitor_rates)->status ?? 0) === 1;
    }

    /**
     * Проверяет, активен ли источник "Формула" для направления.
     *
     * Условия:
     * - указан id_parser_formula_rate (id > 0 / не пусто)
     * - связанная сущность parser_formula_rates имеет статус = 1
     *
     * @param object $dir Объект направления (ожидаются id_parser_formula_rate и parser_formula_rates).
     * @return bool true — источник доступен, false — недоступен.
     */
    public static function formula(object $dir): bool
    {
        $id = (string) ($dir->id_parser_formula_rate ?? '');
        if ($id === '' || $id === '0') {
            return false;
        }

        return (int) (optional($dir->parser_formula_rates)->status ?? 0) === 1;
    }

    /**
     * Проверяет, активен ли ручной курс для направления.
     *
     * Логика:
     * - берём raw-значение manual_rate_value
     * - отсеиваем пустые/NaN/Inf/Infinity/∞
     * - нормализуем число через Brick в десятичную строку (поддерживается scientific notation)
     * - активен, если значение строго больше 0
     *
     * @param object $dir Объект направления (ожидается manual_rate_value).
     * @return bool true — ручной курс доступен, false — недоступен.
     */
    public static function manual(object $dir): bool
    {
        $scale = (int) iEXSetting('max_decimal_places', 18);

        $raw = trim((string) ($dir->manual_rate_value ?? ''));
        if (
            $raw === '' ||
            strcasecmp($raw, 'nan') === 0 ||
            strcasecmp($raw, 'inf') === 0 ||
            strcasecmp($raw, 'infinity') === 0 ||
            $raw === '∞'
        ) {
            return false;
        }

        // Доступ к protected методу трейта из статического контекста — через инстанс.
        $self = new self();

        // Нормализуем в десятичную строку без потери точности.
        $value = $self->toBcString($raw, $scale);

        // Если нормализация дала 0 — ручной курс неактивен.
        if ($value === '0') {
            return false;
        }

        try {
            // true, если строго > 0
            return BigDecimal::of($value)->compareTo(BigDecimal::of('0')) === 1;
        } catch (MathException) {
            return false;
        }
    }

    /**
     * Проверяет, активен ли источник "Крипто-парсер" для направления.
     *
     * Условия:
     * - указан id_crypto_parser (id > 0 / не пусто)
     * - связанная сущность parser_exchange имеет статус = 1
     *
     * @param object $dir Объект направления (ожидаются id_crypto_parser и parser_exchange).
     * @return bool true — источник доступен, false — недоступен.
     */
    public static function crypto(object $dir): bool
    {
        $id = (string) ($dir->id_crypto_parser ?? '');
        if ($id === '' || $id === '0') {
            return false;
        }

        return (int) (optional($dir->parser_exchange)->status ?? 0) === 1;
    }

    public static function fileName(object $dir): string
    {
        return (string) ($dir->file_parser_rates?->file_parser_group?->name ?? 'File parser');
    }

    public static function competitorName(object $dir): string
    {
        return (string) ($dir->competitor_rates?->competitor_link?->name ?? 'Competitor parser');
    }

    public static function formulaName(object $dir): string
    {
        return 'Парсинг по формуле';
    }

    public static function manualName(object $dir): string
    {
        return 'Ручной курс';
    }

    public static function cryptoName(object $dir): string
    {
        return (string) ($dir->parser_exchange?->group_parse_exchange?->name ?? 'Crypto parser');
    }
}
