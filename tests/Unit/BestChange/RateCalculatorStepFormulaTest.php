<?php
declare(strict_types=1);

namespace Tests\Unit\BestChange;

use App\Models\BestChangeDirection;
use App\Services\Calculator\CalculatorMathService;
use iEXPackages\BestChange\DTO\RateSelectionPolicy;
use iEXPackages\BestChange\Services\RateCalculator;
use PHPUnit\Framework\TestCase;

final class RateCalculatorStepFormulaTest extends TestCase
{
    public function test_existing_step_syntax(): void
    {
        $cases = [
            ['+0.1%', '100.1'],
            ['-1%', '99'],
            ['*1.02', '102'],
            ['/1.01', '99.009900990099009901'],
        ];
        foreach ($cases as [$step, $expected]) {
            $math = new CalculatorMathService('100');
            $math->applyStep($step);
            $this->assertSame(0, bccomp($math->getSumma(), $expected, 12), "step {$step} got ".$math->getSumma());
        }
    }

    public function test_formula_present_wins_over_value_and_step(): void
    {
        $direction = new BestChangeDirection([
            'step' => '+10%',
            'formula_value' => '[pos:1]',
        ]);
        $policy = new RateSelectionPolicy('rate', 'api', 'position', 5, 600);
        $rows = [['changer' => 1, 'rate' => '1.5']];
        $calc = new RateCalculator();
        $computed = $calc->calculate($direction, $rows[0], $rows, $policy, [], [1 => 'Ex']);
        $this->assertNotNull($computed);
        $this->assertSame(0, bccomp($computed->rateValue, '1.5', 12));
        $this->assertSame('0', $computed->rateValueWithoutStep);
    }

    public function test_formula_absent_keeps_invert_and_step_path(): void
    {
        $direction = new BestChangeDirection([
            'step' => '+0.1%',
            'formula_value' => '',
        ]);
        $policy = new RateSelectionPolicy('rate', 'api', 'position', 5, 600);
        $row = ['changer' => 7, 'rate' => '0.01'];
        $calc = new RateCalculator();
        $computed = $calc->calculate($direction, $row, [$row], $policy, [], [7 => 'Ex']);
        $this->assertNotNull($computed);
        $this->assertSame(0, bccomp($computed->rateValue, '100.1', 12));
        $this->assertSame(0, bccomp($computed->rateValueWithoutStep, '100', 12));
        $this->assertSame('Ex', $computed->sourceName);
    }
}
