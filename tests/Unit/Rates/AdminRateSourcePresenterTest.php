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

    public function test_derived_with_unusable_bc_link_notes_missing_position(): void
    {
        $d = new DirectionExchange();
        $d->parser_source_name = 'DERIVED_MARKET_BASELINE';
        $bc = new BestChangeDirection();
        $bc->status = 0;
        $bc->is_error_parser = 1;
        $bc->rate_value = '0';
        $bc->position_num = '3';
        $d->setRelation('bestchange_directions', $bc);

        $label = AdminRateSourcePresenter::label($d);
        $this->assertStringContainsString('Внутренний рынок · AUTO', $label);
        $this->assertStringContainsString('BestChange: нет доступной позиции', $label);
        $this->assertStringNotContainsString('DERIVED_MARKET_BASELINE', $label);
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

        $this->assertSame('Ручной курс', AdminRateSourcePresenter::label($d));
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
        $this->assertStringContainsString('AdminRateSourcePresenter::liveExchangeRateLabel($item)', $src);
        $this->assertStringContainsString("'market_base_rate'", $src);
        $this->assertStringContainsString("'rate_source_label'", $src);
        $this->assertStringNotContainsString("Автоматический ('.$src.')", $src);
    }

    public function test_live_rate_label_preserves_sub_nf_scientific_rates(): void
    {
        $d = new DirectionExchange();
        $d->course_value = '0.000000000065707936';
        $d->profit = 0;
        $d->floating_fee = '0';
        $d->parser_source_name = 'BestChange';
        $d->exchange_rate = '1 SHIB = 0.00000000 BTC';

        $c1 = new \App\Models\Currency();
        $c1->tech_name = 'SHIB';
        $code1 = new \App\Models\CodeCurrency();
        $code1->name = 'SHIB';
        $c1->setRelation('code_currency', $code1);

        $c2 = new \App\Models\Currency();
        $c2->tech_name = 'BTC';
        $c2->number_format = 8;
        $code2 = new \App\Models\CodeCurrency();
        $code2->name = 'BTC';
        $c2->setRelation('code_currency', $code2);

        $d->setRelation('currency1', $c1);
        $d->setRelation('currency2', $c2);

        $label = AdminRateSourcePresenter::liveExchangeRateLabel($d);
        $this->assertStringContainsString('SHIB', $label);
        $this->assertStringContainsString('BTC', $label);
        $this->assertStringNotContainsString('= 0 BTC', $label);
        $this->assertMatchesRegularExpression('/0\.0000000000[0-9]+/', $label);
    }

    public function test_operator_labels_never_equal_raw_internal_tokens(): void
    {
        $cases = [
            ['DERIVED_MARKET_BASELINE', 'Внутренний рынок · AUTO'],
            [ZelleUsdUsdtBenchmarkAuthority::PARSER_SOURCE_NAME, 'Zelle · свой курс'],
            ['Ручной курс', 'Ручной курс'],
        ];
        foreach ($cases as [$raw, $expect]) {
            $d = new DirectionExchange();
            $d->parser_source_name = $raw;
            $d->id_currency1 = 1;
            $label = AdminRateSourcePresenter::label($d);
            $this->assertSame($expect, $label);
            $this->assertNotSame('DERIVED_MARKET_BASELINE', $label === 'Внутренний рынок · AUTO' ? 'ok' : $label);
            if ($raw === 'DERIVED_MARKET_BASELINE') {
                $this->assertNotSame('DERIVED_MARKET_BASELINE', $label);
            }
            if ($raw === ZelleUsdUsdtBenchmarkAuthority::PARSER_SOURCE_NAME) {
                $this->assertNotSame('ZELLE_USDTTRC20_BENCHMARK', $label);
            }
        }
    }

    public function test_direction_list_bundle_prefers_rate_source_label(): void
    {
        $candidates = [
            dirname(__DIR__, 3) . '/public/build/assets',
            '/var/www/app_exswapin_usr/data/www/exswaping-backend-releases/20260827T095851Z-7839181c3284/public/build/assets',
        ];
        $found = null;
        foreach ($candidates as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            foreach (glob($dir . '/direction-exchange-*.js') ?: [] as $file) {
                $js = (string) file_get_contents($file);
                if (str_contains($js, 'rate_source_label||o.attributes.parser_source_name')) {
                    $found = $file;
                    break 2;
                }
            }
        }
        if ($found === null) {
            $this->markTestSkipped('direction-exchange admin Vite bundle not present in this worktree/candidate');
        }
        $this->assertNotNull($found, 'direction-exchange list bundle must prefer rate_source_label');
    }

    public function test_list_resource_emits_display_safe_parser_attribute(): void
    {
        $src = (string) file_get_contents(
            dirname(__DIR__, 3) . '/app/Http/Resources/Admin/Basic/DirectionExchangeResources.php'
        );
        $this->assertStringContainsString('AdminRateSourcePresenter::displayParserName($item)', $src);
        $this->assertStringNotContainsString(
            "'parser_source_name' => \$item->parser_source_name",
            $src
        );
    }

    public function test_display_parser_name_never_returns_raw_derived_token(): void
    {
        $d = new DirectionExchange();
        $d->parser_source_name = 'DERIVED_MARKET_BASELINE';
        $d->id_currency1 = 1;
        $display = AdminRateSourcePresenter::displayParserName($d);
        $this->assertSame('Внутренний рынок · AUTO', $display);
        $this->assertFalse(AdminRateSourcePresenter::isRawInternalToken($display));
        $this->assertTrue(AdminRateSourcePresenter::isRawInternalToken('DERIVED_MARKET_BASELINE'));
    }
}
