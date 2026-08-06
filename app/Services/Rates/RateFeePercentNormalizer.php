<?php

declare(strict_types=1);

namespace App\Services\Rates;

/**
 * Release A: every fix_fee / floating_fee is an explicit percentage.
 *
 * Legacy bare numbers such as "-3" mean -3%, never absolute destination units.
 * Optional trailing "%" is accepted and stripped. Absolute arithmetic is forbidden.
 */
final class RateFeePercentNormalizer
{
    public const CLASS_VALID_NUMERIC_PERCENT = 'VALID_NUMERIC_PERCENT';
    public const CLASS_VALID_PERCENT_STRING = 'VALID_PERCENT_STRING';
    public const CLASS_ZERO = 'ZERO';
    public const CLASS_NULL = 'NULL';
    public const CLASS_MALFORMED = 'MALFORMED';
    public const CLASS_EXTREME = 'EXTREME';
    public const CLASS_AMBIGUOUS = 'AMBIGUOUS';

    /** Soft bound for owner review — not an automatic reject. */
    private const EXTREME_ABS_PERCENT = 50.0;

    /**
     * @return array{
     *   ok: bool,
     *   classification: string,
     *   percent: ?string,
     *   expression: ?string,
     *   reason: ?string
     * }
     */
    public function normalize(null|string|int|float $raw): array
    {
        if ($raw === null) {
            return $this->result(true, self::CLASS_NULL, '0', '0%', null);
        }

        $trimmed = trim((string) $raw);
        if ($trimmed === '' || strcasecmp($trimmed, 'null') === 0) {
            return $this->result(true, self::CLASS_NULL, '0', '0%', null);
        }

        $hadPercentSuffix = str_ends_with($trimmed, '%');
        if ($hadPercentSuffix) {
            $trimmed = trim(substr($trimmed, 0, -1));
        }

        // Reject operator forms / formulas — only a signed decimal percent is allowed.
        if ($trimmed === '' || $trimmed === '+' || $trimmed === '-') {
            return $this->result(false, self::CLASS_MALFORMED, null, null, 'empty_numeric');
        }

        if (preg_match('/[^\d.+-]/', $trimmed)) {
            return $this->result(false, self::CLASS_MALFORMED, null, null, 'non_percent_expression');
        }

        if (!preg_match('/^[+-]?\d+(\.\d+)?$/', $trimmed)) {
            return $this->result(false, self::CLASS_AMBIGUOUS, null, null, 'ambiguous_numeric_token');
        }

        if (!is_numeric($trimmed)) {
            return $this->result(false, self::CLASS_MALFORMED, null, null, 'not_numeric');
        }

        $asFloat = (float) $trimmed;
        $percent = $this->toBcString($trimmed);

        if (bccomp($percent, '0', 18) === 0) {
            return $this->result(true, self::CLASS_ZERO, '0', '0%', null);
        }

        $classification = $hadPercentSuffix
            ? self::CLASS_VALID_PERCENT_STRING
            : self::CLASS_VALID_NUMERIC_PERCENT;

        if (abs($asFloat) >= self::EXTREME_ABS_PERCENT) {
            $classification = self::CLASS_EXTREME;
        }

        // Always include % so CalculatorMathService never treats as absolute.
        return $this->result(true, $classification, $percent, $percent . '%', null);
    }

    public function toPercentExpression(null|string|int|float $raw): string
    {
        $n = $this->normalize($raw);
        if (!$n['ok'] || $n['expression'] === null) {
            throw new \InvalidArgumentException(
                'Malformed rate fee value: ' . ($n['reason'] ?? 'unknown')
            );
        }

        return $n['expression'];
    }

    /**
     * @return array{expression: string, ok: bool, classification: string, reason: ?string, percent: string}
     */
    public function toPercentExpressionOrZero(null|string|int|float $raw): array
    {
        $n = $this->normalize($raw);
        if (!$n['ok'] || $n['expression'] === null) {
            return [
                'expression' => '0%',
                'ok' => false,
                'classification' => $n['classification'],
                'reason' => $n['reason'],
                'percent' => '0',
            ];
        }

        return [
            'expression' => $n['expression'],
            'ok' => true,
            'classification' => $n['classification'],
            'reason' => null,
            'percent' => (string) $n['percent'],
        ];
    }

    /**
     * final = base × (1 + percent/100)
     */
    public function applyPercentToRate(string $baseRate, string $percent, int $scale = 18): string
    {
        $base = $this->toBcString($baseRate);
        if (bccomp($base, '0', $scale) <= 0) {
            return '0';
        }

        $pct = $this->toBcString($percent);
        $factor = bcadd('1', bcdiv($pct, '100', $scale + 4), $scale + 4);

        return bcmul($base, $factor, $scale);
    }

    /**
     * @return array{ok: bool, classification: string, percent: ?string, expression: ?string, reason: ?string}
     */
    private function result(
        bool $ok,
        string $classification,
        ?string $percent,
        ?string $expression,
        ?string $reason
    ): array {
        return [
            'ok' => $ok,
            'classification' => $classification,
            'percent' => $percent,
            'expression' => $expression,
            'reason' => $reason,
        ];
    }

    private function toBcString(string $value): string
    {
        $value = trim($value);
        if ($value === '' || !is_numeric($value)) {
            return '0';
        }

        if (stripos($value, 'e') !== false) {
            return sprintf('%.18F', (float) $value);
        }

        return $value;
    }
}
