<?php

declare(strict_types=1);

namespace Tests\Unit\Kyc;

use iEXPackages\KYCPlugin\Drivers\Didit\DiditStatusTransition;
use Tests\TestCase;

final class DiditStatusTransitionTest extends TestCase
{
    /**
     * @return list<array{0:string,1:string,2:bool}>
     */
    public static function transitionMatrix(): array
    {
        return [
            ['not_started', 'pending', true],
            ['pending', 'approved', true],
            ['pending', 'rejected', true],
            ['pending', 'expired', true],
            ['approved', 'approved', true],
            ['approved', 'pending', false],
            ['approved', 'rejected', false],
            ['approved', 'expired', false],
            ['rejected', 'approved', true],
            ['expired', 'approved', true],
            ['abandoned', 'approved', true],
        ];
    }

    /**
     * @dataProvider transitionMatrix
     */
    public function test_monotonic_session_overwrite(string $from, string $to, bool $allowed): void
    {
        $this->assertSame($allowed, DiditStatusTransition::mayOverwriteSession($from, $to));
    }

    public function test_approved_cannot_be_downgraded(): void
    {
        $this->assertFalse(DiditStatusTransition::mayOverwriteSession('approved', 'expired'));
        $this->assertFalse(DiditStatusTransition::mayOverwriteSession('approved', 'rejected'));
        $this->assertFalse(DiditStatusTransition::mayOverwriteSession('approved', 'pending'));
    }
}
