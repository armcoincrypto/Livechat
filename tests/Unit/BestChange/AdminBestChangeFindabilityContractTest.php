<?php

declare(strict_types=1);

namespace Tests\Unit\BestChange;

use App\Services\Rates\BestChangeAdminListingSearch;
use PHPUnit\Framework\TestCase;

final class AdminBestChangeFindabilityContractTest extends TestCase
{
    public function test_yoomoney_search_tokens_and_aliases(): void
    {
        $this->assertSame(
            ['USDTTRC20', 'YooMoney'],
            BestChangeAdminListingSearch::tokens('USDTTRC20→YooMoney')
        );
        $this->assertSame(
            ['1415'],
            BestChangeAdminListingSearch::tokens('1415')
        );
        $this->assertContains('YAMRUB', BestChangeAdminListingSearch::expansions('YooMoney'));
        $this->assertContains('YAMRUB', BestChangeAdminListingSearch::expansions('юмани'));
        $this->assertContains('ЮMoney', BestChangeAdminListingSearch::expansions('yamrub'));
        $this->assertSame(['USDTTRC20'], BestChangeAdminListingSearch::expansions('USDTTRC20'));
    }

    public function test_controller_exposes_search_counts_and_per_page(): void
    {
        $src = (string) file_get_contents(
            dirname(__DIR__, 3) . '/app/Http/Controllers/Administrator/Crypto/BestChangeController.php'
        );
        $this->assertStringContainsString('BestChangeAdminListingSearch::apply', $src);
        $this->assertStringContainsString('total_mapping_rows', $src);
        $this->assertStringContainsString('filtered_total', $src);
        $this->assertStringContainsString('[30, 50, 100]', $src);
        $this->assertStringContainsString("input('q')", $src);
        $this->assertStringContainsString('id_direction_exchange', $src);
        $this->assertStringContainsString('orderByDesc(\'status\')', $src);
        $this->assertStringContainsString('paginate($perPage)', $src);
        $this->assertStringNotContainsString('$query->get()', $src);
    }

    public function test_filter_exposes_q_search_and_direction_id(): void
    {
        $src = (string) file_get_contents(
            dirname(__DIR__, 3) . '/app/Models/Filters/BestchangeDirectionFilter.php'
        );
        $this->assertStringContainsString('function q(', $src);
        $this->assertStringContainsString('function search(', $src);
        $this->assertStringContainsString('function idDirectionExchange(', $src);
        $this->assertStringContainsString('BestChangeAdminListingSearch::apply', $src);
    }

    public function test_hasone_prefers_active_mapping_twin(): void
    {
        $src = (string) file_get_contents(
            dirname(__DIR__, 3) . '/app/Models/DirectionExchange.php'
        );
        $this->assertStringContainsString("ofMany([", $src);
        $this->assertStringContainsString("'status' => 'max'", $src);
        $this->assertStringContainsString("'id' => 'max'", $src);
    }

    public function test_findability_overlay_and_blade_wired(): void
    {
        $js = (string) file_get_contents(
            dirname(__DIR__, 3) . '/public/static/admin-bestchange-findability.js'
        );
        $blade = (string) file_get_contents(
            dirname(__DIR__, 3) . '/resources/views/admin-vue.blade.php'
        );
        $this->assertStringContainsString('searchParams.set', $js);
        $this->assertStringContainsString("out.q = q", $js);
        $this->assertStringContainsString('per_page', $js);
        $this->assertStringContainsString('sessionStorage', $js);
        $this->assertStringContainsString('exs-bc-findability', $js);
        $this->assertStringContainsString('admin-bestchange-findability.js', $blade);
        $this->assertStringNotContainsString('BestChangeDirection::query()->get()', $js);
    }

    public function test_search_service_does_not_write_rates(): void
    {
        $src = (string) file_get_contents(
            dirname(__DIR__, 3) . '/app/Services/Rates/BestChangeAdminListingSearch.php'
        );
        $this->assertStringNotContainsString('->update(', $src);
        $this->assertStringNotContainsString('->save(', $src);
        $this->assertStringNotContainsString('profit', $src);
        $this->assertStringNotContainsString('position_num', $src);
        $this->assertStringContainsString('orWhereHas', $src);
        $this->assertStringContainsString('designation_xml', $src);
        $this->assertStringContainsString('tech_name', $src);
    }
}
