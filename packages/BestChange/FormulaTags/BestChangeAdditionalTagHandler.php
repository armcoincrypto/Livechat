<?php

namespace iEXPackages\BestChange\FormulaTags;

use iEXPackages\Courses\FormulaTags\Contracts\FormulaTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;


class BestChangeAdditionalTagHandler implements FormulaTagHandler
{
    private array $filtered;
    private string $typePosition;

    public function __construct(array $filtered, string $typePosition = 'rate')
    {
        $this->filtered = $filtered;
        $this->typePosition = $typePosition;
    }

    public function handle(FormulaParserService $parser, string $expression, int $precision): string
    {
        $parts = explode(':', $expression, 2);
        if (count($parts) !== 2) {
            return '0';
        }

        [$operation, $positions] = $parts;
        $positions = array_map('trim', explode(',', $positions));

        $values = [];
        foreach ($positions as $pos) {
            $idx = (int)$pos - 1;

            if (!isset($this->filtered[$idx])) {
                continue;
            }

            $item = $this->filtered[$idx];

            switch ($operation) {
                case 'avg':
                case 'diff':
                case 'median':
                case 'spread':
                case 'minrate':
                case 'maxrate':
                    $values[] = $item[$this->typePosition] ?? null;
                    break;

                case 'reserve':
                case 'reserve_min':
                case 'reserve_max':
                    $values[] = $item['reserve'] ?? null;
                    break;

                case 'inmin':
                    $values[] = $item['inmin'] ?? null;
                    break;

                case 'inmax':
                    $values[] = $item['inmax'] ?? null;
                    break;

                case 'rate':
                    return (string)($item['rate'] ?? '0');

                case 'rankrate':
                    return (string)($item['rankrate'] ?? '0');
            }
        }

        // Удаляем null значения
        $values = array_filter($values, fn($val) => $val !== null);

        if (empty($values)) {
            return '0';
        }

        return match ($operation) {
            'avg'         => bcdiv(array_sum($values), (string)count($values), $precision),
            'diff'        => count($values) === 2 ? bcsub($values[0], $values[1], $precision) : '0',
            'median'      => $this->calculateMedian($values, $precision),
            'spread'      => bcsub(max($values), min($values), $precision),
            'minrate'     => min($values),
            'maxrate'     => max($values),
            'reserve'     => array_sum($values),
            'reserve_min' => min($values),
            'reserve_max' => max($values),
            'inmin'       => min($values),
            'inmax'       => max($values),
            default       => '0',
        };
    }

    private function calculateMedian(array $values, int $precision): string
    {
        sort($values, SORT_NUMERIC);
        $count = count($values);
        $middle = (int)($count / 2);

        if ($count % 2) {
            return (string)$values[$middle];
        }

        return bcdiv(bcadd($values[$middle - 1], $values[$middle], $precision), '2', $precision);
    }
}
