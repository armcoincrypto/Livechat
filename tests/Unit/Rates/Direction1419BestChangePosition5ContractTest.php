<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Models\BestChangeDirection;
use App\Models\DirectionExchange;
use App\Services\Rates\AdminRateSourcePresenter;
use App\Services\Rates\DerivedMarketBaselineAuthority;
use App\Services\Rates\ProtectedMarketBaselineWriteGuard;
use iEXPackages\BestChange\Services\PositionResolver;
use Tests\TestCase;

/**
 * Contract: BTC→YooMoney (1419) owner-authorized BestChange position 5.
 *
 * Protected Derived + saved BC position must not affect the live rate until
 * block_bestchange_overwrite is cleared and BC status=1. After that, position
 * is authoritative; derived --apply must skip bestchange_active.
 */
final class Direction1419BestChangePosition5ContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ProtectedMarketBaselineWriteGuard::clearCache();
    }

    public function test_1419_config_no_longer_blocks_bestchange_overwrite(): void
    {
        $cfg = DerivedMarketBaselineAuthority::fromStorageApp()->config()['directions']['1419'] ?? null;
        $this->assertIsArray($cfg);
        $this->assertSame('BTC', $cfg['from'] ?? null);
        $this->assertSame('YAMRUB', $cfg['to'] ?? null);
        $this->assertFalse(
            (bool) ($cfg['ownership']['block_bestchange_overwrite'] ?? true),
            '1419 must allow BestChange ownership after owner-authorized canary'
        );
        $this->assertFalse(ProtectedMarketBaselineWriteGuard::blocksBestchangeOverwrite(1419));
    }

    public function test_admin_label_uses_position_5_when_bc_active(): void
    {
        $d = new DirectionExchange();
        $d->parser_source_name = 'BestChange';
        $bc = new BestChangeDirection();
        $bc->status = 1;
        $bc->position_num = '5';
        $d->setRelation('bestchange_directions', $bc);

        $this->assertSame('BestChange · позиция 5', AdminRateSourcePresenter::label($d));
        $this->assertNotSame('Внутренний рынок · AUTO', AdminRateSourcePresenter::label($d));
        $this->assertNotSame('DERIVED_MARKET_BASELINE', AdminRateSourcePresenter::label($d));
    }

    public function test_position_resolver_selects_fifth_valid_slot(): void
    {
        $resolver = new PositionResolver();
        $dir = new BestChangeDirection(['position_num' => '5']);
        $this->assertSame(5, $resolver->requestedPosition($dir, 1, 600));
        $this->assertSame(5, $resolver->resolve($dir, 90, 1, 600));
        $this->assertSame(5, $resolver->resolve($dir, 5, 1, 600));
    }

    public function test_protected_peer_still_blocks_while_1419_is_open(): void
    {
        // GRAM USDT peer remains protected — canary must not loosen global guard.
        $this->assertTrue(ProtectedMarketBaselineWriteGuard::blocksBestchangeOverwrite(11));
        $this->assertFalse(ProtectedMarketBaselineWriteGuard::blocksBestchangeOverwrite(1419));
    }
}
