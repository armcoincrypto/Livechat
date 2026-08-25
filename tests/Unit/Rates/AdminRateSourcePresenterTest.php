<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Models\BestChangeDirection;
use App\Models\DirectionExchange;
use App\Services\Rates\AdminRateSourcePresenter;
use App\Services\Rates\ZelleUsdUsdtBenchmarkAuthority;
use PHPUnit\Framework\TestCase;

final class AdminRateSourcePresenterTest extends TestCase
{
    public function test_bestchange_includes_position_when_active_relation_loaded(): void
    {
        $d = new DirectionExchange();
        $d->parser_source_name = 'BestChange';
        $d->id_currency1 = 1;

        $bc = new BestChangeDirection();
        $bc->status = 1;
        $bc->position_num = '2';
        $d->setRelation('bestchange_directions', $bc);

        $this->assertSame('BestChange · позиция 2', AdminRateSourcePresenter::label($d));
        $this->assertSame('BestChange', $d->parser_source_name);
    }

    public function test_derived_uses_internal_market_label_without_changing_parser(): void
    {
        $d = new DirectionExchange();
        $d->parser_source_name = 'DERIVED_MARKET_BASELINE';
        $d->id_currency1 = 1;

        $this->assertSame('Внутренний рынок · AUTO', AdminRateSourcePresenter::label($d));
        $this->assertSame('DERIVED_MARKET_BASELINE', $d->parser_source_name);
    }

    public function test_zelle_uses_own_rate_label(): void
    {
        $d = new DirectionExchange();
        $d->parser_source_name = ZelleUsdUsdtBenchmarkAuthority::PARSER_SOURCE_NAME;
        $d->id_currency1 = 87;

        $this->assertSame('Zelle · свой курс', AdminRateSourcePresenter::label($d));
        $this->assertSame(ZelleUsdUsdtBenchmarkAuthority::PARSER_SOURCE_NAME, $d->parser_source_name);
    }

    public function test_manual_stays_manual(): void
    {
        $d = new DirectionExchange();
        $d->parser_source_name = 'Ручной курс';
        $d->id_currency1 = 1;

        $this->assertSame('Ручной', AdminRateSourcePresenter::label($d));
    }

    public function test_bestchange_without_relation_does_not_invent_position(): void
    {
        $d = new DirectionExchange();
        $d->parser_source_name = 'BestChange';
        $d->id_currency1 = 1;

        $this->assertSame('BestChange', AdminRateSourcePresenter::label($d));
    }

    public function test_list_resource_delegates_to_presenter(): void
    {
        $src = (string) file_get_contents(
            dirname(__DIR__, 3) . '/app/Http/Resources/Admin/Basic/DirectionExchangeResources.php'
        );
        $this->assertStringContainsString('AdminRateSourcePresenter::label($item)', $src);
        $this->assertStringNotContainsString("Автоматический ('.$src.')", $src);
    }
}
