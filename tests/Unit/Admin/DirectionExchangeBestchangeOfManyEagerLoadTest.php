<?php

declare(strict_types=1);

namespace Tests\Unit\Admin;

use App\Http\Controllers\Administrator\Basic\AdminPricingHealthController;
use App\Http\Controllers\Administrator\Basic\DirectionExchangeController;
use App\Models\BestChangeDirection;
use App\Models\DirectionExchange;
use App\Models\User;
use App\Services\Rates\AdminPricingTransparency;
use App\Settings\DirectionConfig;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class DirectionExchangeBestchangeOfManyEagerLoadTest extends TestCase
{
    private const LIST_SELECT = 'id,id_direction_exchange,status,position_num,updated_at,rate_value,is_error_parser';

    private const EDIT_SELECT = 'id,id_direction_exchange,status,position_num';

    /** @var list<int> */
    private const REPRESENTATIVE_IDS = [15, 35, 41, 1415];

    public function test_ofMany_preference_and_qualify_hook_remain(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3) . '/app/Models/DirectionExchange.php');
        $this->assertStringContainsString("ofMany([", $src);
        $this->assertStringContainsString("'status' => 'max'", $src);
        $this->assertStringContainsString("'id' => 'max'", $src);
        $this->assertStringContainsString('qualifyBestchangeDirectionSelectColumns', $src);
        $this->assertStringContainsString('beforeQuery', $src);
    }

    public function test_qualify_helper_prefixes_bare_id_direction_exchange(): void
    {
        $out = DirectionExchange::qualifyBestchangeDirectionSelectColumns([
            'id',
            'id_direction_exchange',
            'status',
            'position_num',
        ]);
        $this->assertSame([
            'bestchange_directions.id',
            'bestchange_directions.id_direction_exchange',
            'bestchange_directions.status',
            'bestchange_directions.position_num',
        ], $out);
    }

    public function test_qualify_helper_leaves_qualified_and_expressions(): void
    {
        $out = DirectionExchange::qualifyBestchangeDirectionSelectColumns([
            'bestchange_directions.id',
            'bestchange_directions.*',
            'count(id)',
            'id as mapping_id',
        ]);
        $this->assertSame([
            'bestchange_directions.id',
            'bestchange_directions.*',
            'count(id)',
            'id as mapping_id',
        ], $out);
        $this->assertNull(DirectionExchange::qualifyBestchangeDirectionSelectColumns(null));
    }

    public function test_list_eager_load_does_not_emit_ambiguous_sql(): void
    {
        DB::enableQueryLog();
        try {
            $rows = DirectionExchange::query()
                ->select('id')
                ->with(['bestchange_directions:' . self::LIST_SELECT])
                ->whereIn('id', self::REPRESENTATIVE_IDS)
                ->get();
        } catch (QueryException $e) {
            $this->fail('list eager-load raised QueryException: ' . $e->getMessage());
        }

        $this->assertCount(4, $rows);
        $this->assertNoAmbiguousIdDirectionExchange(DB::getQueryLog());
        foreach ($rows as $row) {
            $this->assertTrue($row->relationLoaded('bestchange_directions'));
        }
    }

    public function test_main_edit_eager_load_does_not_emit_ambiguous_sql(): void
    {
        DB::enableQueryLog();
        try {
            $item = DirectionExchange::query()
                ->with(['bestchange_directions:' . self::EDIT_SELECT])
                ->findOrFail(15);
        } catch (QueryException $e) {
            $this->fail('edit eager-load raised QueryException: ' . $e->getMessage());
        }

        $this->assertSame(15, (int) $item->id);
        $this->assertTrue($item->relationLoaded('bestchange_directions'));
        $bc = $item->bestchange_directions;
        $this->assertInstanceOf(BestChangeDirection::class, $bc);
        $this->assertSame(15, (int) $bc->id_direction_exchange);
        $this->assertNoAmbiguousIdDirectionExchange(DB::getQueryLog());
    }

    public function test_duplicate_active_inactive_mapping_selects_active_deterministically(): void
    {
        $twin = DB::table('bestchange_directions')
            ->select('id_direction_exchange')
            ->selectRaw('SUM(status = 1) as active_n')
            ->selectRaw('SUM(status = 0) as inactive_n')
            ->selectRaw('MAX(CASE WHEN status = 1 THEN id END) as active_id')
            ->selectRaw('MAX(CASE WHEN status = 0 THEN id END) as inactive_id')
            ->groupBy('id_direction_exchange')
            ->havingRaw('COUNT(*) > 1')
            ->havingRaw('SUM(status = 1) >= 1')
            ->havingRaw('SUM(status = 0) >= 1')
            ->orderBy('id_direction_exchange')
            ->first();
        $this->assertNotNull($twin, 'need an active/inactive mapping twin');

        $dirId = (int) $twin->id_direction_exchange;
        $activeId = (int) $twin->active_id;
        $inactiveId = (int) $twin->inactive_id;
        $this->assertGreaterThan(0, $activeId);
        $this->assertGreaterThan(0, $inactiveId);
        $this->assertNotSame($activeId, $inactiveId);

        $item = DirectionExchange::query()
            ->with(['bestchange_directions:' . self::EDIT_SELECT])
            ->findOrFail($dirId);
        $bc = $item->bestchange_directions;
        $this->assertInstanceOf(BestChangeDirection::class, $bc);
        $this->assertSame($activeId, (int) $bc->id, 'ofMany must prefer status=max then id=max');
        $this->assertSame(1, (int) $bc->status);
        $this->assertNotSame($inactiveId, (int) $bc->id);
    }

    public function test_admin_list_and_main_edit_return_valid_json(): void
    {
        $this->loginQaAdmin();
        $controller = app(DirectionExchangeController::class);
        $settings = app(DirectionConfig::class);

        $list = $controller->index(Request::create('/iexadmin/frontend-api/basic/direction_exchange', 'GET'), $settings);
        $this->assertSame(200, $list->getStatusCode());
        $listJson = $list->getData(true);
        $this->assertIsArray($listJson);
        $this->assertArrayHasKey('items', $listJson);
        $this->assertArrayHasKey('per_page', $listJson);

        $search = $controller->index(
            Request::create('/iexadmin/frontend-api/basic/direction_exchange', 'GET', [
                'tech_name' => 'USDTTRC20',
                'page' => 1,
            ]),
            $settings
        );
        $this->assertSame(200, $search->getStatusCode());
        $this->assertIsArray($search->getData(true));

        $filtered = $controller->index(
            Request::create('/iexadmin/frontend-api/basic/direction_exchange', 'GET', [
                'status' => [1],
                'page' => 2,
            ]),
            $settings
        );
        $this->assertSame(200, $filtered->getStatusCode());
        $filteredJson = $filtered->getData(true);
        $this->assertIsArray($filteredJson);
        $this->assertArrayHasKey('items', $filteredJson);

        $edit = $controller->edit(15, Request::create(
            '/iexadmin/frontend-api/basic/direction_exchange/15/edit',
            'GET',
            ['pageName' => 'main']
        ));
        $this->assertSame(200, $edit->getStatusCode());
        $editJson = $edit->getData(true);
        $this->assertIsArray($editJson);
    }

    public function test_representative_directions_detail_explain_and_mapping(): void
    {
        $this->loginQaAdmin();
        $controller = app(DirectionExchangeController::class);
        $health = app(AdminPricingHealthController::class);

        foreach (self::REPRESENTATIVE_IDS as $id) {
            $edit = $controller->edit($id, Request::create(
                '/iexadmin/frontend-api/basic/direction_exchange/' . $id . '/edit',
                'GET',
                ['pageName' => 'main']
            ));
            $this->assertSame(200, $edit->getStatusCode(), 'edit ' . $id);
            $this->assertIsArray($edit->getData(true), 'edit json ' . $id);

            $explain = $health->explain($id);
            $this->assertSame(200, $explain->getStatusCode(), 'explain ' . $id);
            $payload = $explain->getData(true);
            $this->assertIsArray($payload);
            $this->assertArrayHasKey('data', $payload);

            $item = DirectionExchange::query()
                ->with(['bestchange_directions:' . self::LIST_SELECT])
                ->findOrFail($id);
            $bc = $item->bestchange_directions;
            $this->assertInstanceOf(BestChangeDirection::class, $bc, 'mapping ' . $id);
            $this->assertSame($id, (int) $bc->id_direction_exchange);
            $this->assertSame(1, (int) $bc->status);

            $direct = AdminPricingTransparency::explain($item);
            $this->assertIsArray($direct);
        }
    }

    private function loginQaAdmin(): void
    {
        Event::fake();
        $admin = User::query()->find(920970352)
            ?: User::permission('allow_admin')->orderBy('id')->first()
            ?: User::orderBy('id')->first();
        $this->assertNotNull($admin, 'need an administrator to exercise admin controllers');
        Auth::login($admin);
    }

    /**
     * @param  list<array{query?:string,sql?:string}>  $log
     */
    private function assertNoAmbiguousIdDirectionExchange(array $log): void
    {
        $joined = '';
        foreach ($log as $entry) {
            $joined .= ' ' . (string) ($entry['query'] ?? $entry['sql'] ?? '');
        }
        $this->assertStringNotContainsString(
            'Column \'id_direction_exchange\' in field list is ambiguous',
            $joined
        );
        $this->assertDoesNotMatchRegularExpression(
            '/select `id`, `id_direction_exchange`/',
            $joined,
            'constrained ofMany select must qualify id_direction_exchange'
        );
        $this->assertStringContainsString('bestchange_directions`.`id_direction_exchange', $joined);
    }
}
