<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administrator\Basic;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Basic\ReserveLedgerResources;
use App\Http\Resources\Admin\Basic\ReservesResources;
use App\Models\Currency;
use App\Models\Reserve;
use App\Models\ReserveFile;
use App\Models\ReserveLedger;
use App\Models\ReserveManualEvent;
use App\Services\Reserves\ReserveLinkManager;
use App\Services\Reserves\ReserveLinkResolver;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * ReservesController
 *
 * Админский контроллер резервов:
 * - список корневых резервов
 * - дерево связанных резервов любой глубины (через closure table)
 * - редактирование суммы
 * - привязка/отвязка резервов
 * - просмотр истории (ledger)
 */
class ReservesController extends Controller
{
    /**
     * Настройки страницы резервов.
     *
     * @var array<string, array<int,string>>
     */
    protected array $settingOptions = [
        'settings_columns' => [
            'admin_reserves_pagination',
            'admin_reserves_hidden_columns',
        ],
        'settings' => [
            'is_enabled_reserves_from_server',
            'is_enabled_reserves_from_file',
            'max_number_format_reserve',
        ],
    ];

    /**
     * Список резервов (корни) + дерево связанных резервов.
     *
     * Возвращает:
     * - корневые резервы постранично
     * - children_tree: дерево потомков
     * - children_total_count: общее число связанных резервов
     */
    public function index(Request $request): JsonResponse
    {
        // Автосоздание резервов для активных валют
        $activeCurrencyIds = Currency::active()->pluck('id')->all();

        $existingReserveCurrencyIds = Reserve::query()
            ->whereIn('id_currency', $activeCurrencyIds)
            ->pluck('id_currency')
            ->all();

        $missingCurrencyIds = array_values(array_diff($activeCurrencyIds, $existingReserveCurrencyIds));

        if (!empty($missingCurrencyIds)) {
            $now = now();
            $userId = (int) auth()->id();

            $rows = array_map(static fn (int $currencyId) => [
                'id_currency' => $currencyId,
                'summa' => '0',
                'id_user' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ], $missingCurrencyIds);

            Reserve::query()->insertOrIgnore($rows);
        }

        // settings
        if ($request->has('showPage') && $request->get('showPage') === 'settings') {
            return response()->json([
                'max_number_format_reserve' => (int) iEXSetting('max_number_format_reserve', 10),
                'is_enabled_reserves_from_file' => (int) iEXSetting('is_enabled_reserves_from_file', 0),
                'is_enabled_reserves_from_server' => (int) iEXSetting('is_enabled_reserves_from_server', 0),
            ]);
        }

        // currencies selector
        if ($request->has('id_loading_currency')) {
            $currencies = Currency::active()
                ->select('id', 'tech_name', 'status')
                ->get()
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'value' => $item->tech_name,
                ])->values();

            return response()->json($currencies);
        }

        // Корни
        $query = Reserve::filter($request->all())
            ->with([
                'user:id,name',
                'currency:id,id_code_currency,id_payment,number_format,tech_name',
                'currency.code_currency:id,name',
                'currency.payment:id,name,logo',
                'link:reserve_id,parent_reserve_id,is_active,note',
            ])
            ->orderBy('sorting');

        $query->whereDoesntHave('link', function ($q) {
            $q->whereNotNull('parent_reserve_id');
        });

        $paginator = $query->paginate(20);

        /** @var \Illuminate\Support\Collection<int, Reserve> $roots */
        $roots = collect($paginator->items());

        if ($roots->isEmpty()) {
            return response()->json([
                'items' => new ReservesResources($paginator),
                'selected_columns' => [],
                'per_page' => (int) iEXSetting('admin_reserves_pagination', 20),
            ]);
        }

        $rootIds = $roots->pluck('id')->map(fn ($v) => (int) $v)->all();

        /**
         * ВАЖНО:
         * - leftJoin reserve_links — чтобы потомок не пропал из дерева, даже если у него нет записи в reserve_links.
         * - parent_reserve_id в таком случае будет NULL → мы fallback-приклеим к корню.
         */
        $rows = DB::table('reserve_closures as rc')
            ->leftJoin('reserve_links as lnk', 'lnk.reserve_id', '=', 'rc.descendant_id')
            ->join('reserves as r', 'r.id', '=', 'rc.descendant_id')
            ->leftJoin('currencies as c', 'c.id', '=', 'r.id_currency')
            ->leftJoin('code_currency as cc', 'cc.id', '=', 'c.id_code_currency')
            ->leftJoin('payments as p', 'p.id', '=', 'c.id_payment')
            ->whereIn('rc.ancestor_id', $rootIds)
            ->where('rc.depth', '>', 0)
            ->select([
                'rc.ancestor_id as root_id',
                'rc.depth',

                'rc.descendant_id as reserve_id',
                'lnk.parent_reserve_id',
                'lnk.is_active',
                'lnk.note',

                'r.summa',
                'r.updated_at',

                'c.tech_name as currency_name',
                'cc.name as code_name',
                'p.name as payment_name',
                'p.logo as payment_logo',
            ])
            ->orderBy('rc.ancestor_id')
            ->orderBy('rc.depth')
            ->orderBy('rc.descendant_id')
            ->get();

        $byRoot = [];
        foreach ($rows as $r) {
            $rid = (int) $r->root_id;
            $byRoot[$rid] ??= [];
            $byRoot[$rid][] = $r;
        }

        foreach ($roots as $root) {
            $rid = (int) $root->id;
            $list = $byRoot[$rid] ?? [];

            $nodes = [];
            foreach ($list as $item) {
                $reserveId = (int) $item->reserve_id;

                $nodes[$reserveId] = [
                    'reserve_id' => $reserveId,
                    // если parent NULL (нет строки reserve_links) — приклеиваем к корню
                    'parent_reserve_id' => $item->parent_reserve_id !== null ? (int) $item->parent_reserve_id : $rid,
                    'is_active' => $item->is_active !== null ? (bool) $item->is_active : true,
                    'note' => $item->note,
                    'reserve' => [
                        'id' => $reserveId,
                        'summa' => function_exists('iex_money_normalize')
                            ? iex_money_normalize((string) $item->summa)
                            : (string) ($item->summa ?? '0'),
                        'updated_at' => $item->updated_at ? Carbon::parse($item->updated_at)->toISOString() : null,
                        'currency' => [
                            'name' => $item->currency_name,
                            'code_currency' => ['name' => $item->code_name],
                            'payment' => [
                                'name' => $item->payment_name,
                                'logo' => $item->payment_logo ? '/storage/payment_systems/' . $item->payment_logo : null,
                            ],
                        ],
                    ],
                    'children' => [],
                ];
            }

            $tree = [];
            foreach ($nodes as $id => &$node) {
                $pid = $node['parent_reserve_id'];

                if ($pid === $rid) {
                    $tree[] = &$node;
                    continue;
                }

                if ($pid !== null && isset($nodes[$pid])) {
                    $nodes[$pid]['children'][] = &$node;
                    continue;
                }

                $tree[] = &$node;
            }
            unset($node);

            $root->setAttribute('children_tree', $tree);
            $root->setAttribute('children_total_count', count($nodes));
        }

        $admin_hidden_columns = explode(',', (string) iEXSetting('admin_reserves_column_hidden_columns'));
        $allowedColumns = ['currency', 'summa', 'last_updated'];

        return response()->json([
            'items' => new ReservesResources($paginator),
            'selected_columns' => collect($admin_hidden_columns)
                ->map(fn ($item) => $item)
                ->reject(fn ($item) => !in_array($item, $allowedColumns, true))
                ->values(),
            'per_page' => (int) iEXSetting('admin_reserves_pagination', 20),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $reserve = Reserve::query()->find($id);

        if (!$reserve) {
            return response()->json(['status' => 1, 'message' => 'Резерв не найден'], 404);
        }

        $logs = ReserveLedger::query()
            ->where('reserve_id', (int) $reserve->id)
            ->orderByDesc('occurred_at')
            ->paginate(20);

        return response()->json(['items' => new ReserveLedgerResources($logs)]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->has('showPage') && $request->get('showPage') === 'settings') {
            $array = [];
            foreach ($this->settingOptions['settings'] as $value) {
                $array[$value] = $request->has($value) ? (int) $request->get($value) : 0;
            }
            iEXSetting($array);

            return response()->json(['status' => 0, 'message' => 'Настройки сохранены']);
        }

        $validator = Validator::make($request->all(), [
            'id_currency' => ['required', 'integer'],
            'amount' => ['required', 'numeric'],
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 1, 'message' => $validator->messages()->first()]);
        }

        $find = Reserve::query()->where('id_currency', (int) $request->id_currency)->first();

        $old = $find ? (string) $find->summa : '0';
        $add = (string) $request->amount;

        $scale = 18;
        $oldDec = BigDecimal::of($old);
        $addDec = BigDecimal::of($add);

        $newDec = $oldDec->plus($addDec)->toScale($scale, RoundingMode::DOWN);
        $new = $newDec->__toString();

        $reserve = Reserve::query()->updateOrCreate(
            ['id_currency' => (int) $request->id_currency],
            [
                'id_currency' => (int) $request->id_currency,
                'summa' => $new,
                'id_user' => (int) auth()->id(),
            ]
        );

        $this->event([
            'id_reserve' => (int) $reserve->id,
            'reserve_from' => $old,
            'reserve_to' => $new,
            'comment' => '',
        ], 'plus');

        return response()->json(['status' => 0, 'message' => 'Резерв успешно добавлен']);
    }

    public function edit(int $id, Request $request, ReserveLinkResolver $resolver): JsonResponse
    {
        $item = Reserve::query()
            ->with(['currency:id,tech_name', 'link:reserve_id,parent_reserve_id,is_active,note'])
            ->find($id);

        if (!$item) {
            return response()->json(['status' => 1, 'message' => 'Резерв не найден'], 404);
        }

        $reserves = Reserve::query()
            ->where('status', 0)
            ->where('id', '!=', (int) $item->id)
            ->with(['currency:id,tech_name'])
            ->withCount('child_links')
            ->orderBy('sorting')
            ->get();

        $reserveFiles = ReserveFile::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($f) => [
                'id' => $f->id,
                'value' => '[' . ($f->file_group?->name ?? '—') . '] - ' . $f->name . ' (' . $f->amount . ')',
            ])->values();

        $parentId = $item->link?->parent_reserve_id ? (int) $item->link->parent_reserve_id : null;

        return response()->json([
            'id' => (int) $item->id,
            'attributes' => [
                'summa' => (string) $item->summa,
                'is_fixed_reserve' => (int) $item->is_fixed_reserve,
                'id_file_reserve' => (int) $item->id_file_reserve,
                'parent_reserve_id' => $parentId,
                'link_note' => $item->link?->note,
                'link_is_active' => (bool) ($item->link?->is_active ?? true),
            ],
            'meta' => $resolver->getUiMeta((int) $item->id),
            'settings' => [
                'is_enabled_reserves_from_file' => (int) iEXSetting('is_enabled_reserves_from_file'),
            ],
            'reserves' => $reserves->map(fn ($r) => [
                'id' => (int) $r->id,
                'value' => trim(($r->currency?->tech_name ?? '—') . ' (используют: ' . (int) ($r->child_links_count ?? 0) . ')'),
            ])->values(),
            'reserveFiles' => $reserveFiles,
        ]);
    }

    public function update(Request $request, int $id, ReserveLinkManager $linkManager): JsonResponse
    {
        $item = Reserve::query()->with('link:reserve_id,parent_reserve_id,is_active,note')->find($id);

        if (!$item) {
            return response()->json(['status' => 1, 'message' => 'Резерв не найден'], 404);
        }

        // быстрый update суммы
        if ($request->has('is_update') && $request->get('type') === 'summa') {
            $amountValue = (string) ($request->amount ?? '0');

            $scale = 18;
            $old = (string) $item->summa;

            $oldDec = BigDecimal::of($old);
            $newDec = BigDecimal::of($amountValue)->toScale($scale, RoundingMode::DOWN);

            if (!$newDec->isEqualTo($oldDec)) {
                $type = $newDec->isLessThan($oldDec) ? 'minus' : 'plus';

                $this->event([
                    'id_reserve' => (int) $item->id,
                    'reserve_from' => $oldDec->__toString(),
                    'reserve_to' => $newDec->__toString(),
                    'comment' => null,
                ], $type);

                $item->update([
                    'summa' => $newDec->__toString(),
                    'id_user' => (int) auth()->id(),
                ]);
            }

            return response()->json(['status' => 0, 'message' => 'Резервы обновлены']);
        }

        // partial link update (detach/attach)
        if ($request->has('parent_reserve_id') && !$request->has('summa')) {
            $parentIdRaw = $request->input('parent_reserve_id');
            $parentId = ($parentIdRaw !== null && $parentIdRaw !== '') ? (int) $parentIdRaw : null;

            if ($parentId !== null && $parentId === (int) $item->id) {
                $parentId = null;
            }

            $currentParent = $item->link?->parent_reserve_id ? (int) $item->link->parent_reserve_id : null;

            if ($parentId === null && $currentParent !== null) {
                $linkManager->detach((int) $item->id);
            } elseif ($parentId !== null && $parentId !== $currentParent) {
                $linkManager->attach(
                    childId: (int) $item->id,
                    parentId: $parentId,
                    note: (string) $request->input('link_note')
                );
            }

            return response()->json(['status' => 0, 'message' => 'Связь резерва обновлена']);
        }

        // full update
        $validator = Validator::make($request->all(), [
            'summa' => ['required', 'numeric'],
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 1, 'message' => $validator->messages()->first()]);
        }

        $scale = 18;
        $old = BigDecimal::of((string) $item->summa);
        $new = BigDecimal::of((string) $request->get('summa'))->toScale($scale, RoundingMode::DOWN);

        if (!$new->isEqualTo($old)) {
            $type = $new->isLessThan($old) ? 'minus' : 'plus';

            $this->event([
                'id_reserve' => (int) $item->id,
                'reserve_from' => $old->__toString(),
                'reserve_to' => $new->__toString(),
                'comment' => $request->has('comment') ? (string) $request->get('comment') : null,
            ], $type);
        }

        $item->summa = $new->__toString();
        $item->id_user = (int) auth()->id();
        $item->id_group = (int) ($request->get('id_group', 0));
        $item->id_file_reserve = (int) ($request->get('id_file_reserve', 0));
        $item->id_server_reserve = (string) ($request->get('id_server_reserve', 0));
        $item->is_fixed_reserve = (int) ($request->get('is_fixed_reserve', 0));
        $item->save();

        $parentIdRaw = $request->input('parent_reserve_id');
        $parentId = ($parentIdRaw !== null && $parentIdRaw !== '') ? (int) $parentIdRaw : null;

        if ($parentId !== null && $parentId === (int) $item->id) {
            $parentId = null;
        }

        $currentParent = $item->link?->parent_reserve_id ? (int) $item->link->parent_reserve_id : null;

        if ($parentId === null && $currentParent !== null) {
            $linkManager->detach((int) $item->id);
        } elseif ($parentId !== null && $parentId !== $currentParent) {
            $linkManager->attach(
                childId: (int) $item->id,
                parentId: $parentId,
                note: (string) $request->input('link_note')
            );
        }

        return response()->json(['status' => 0, 'message' => 'Резерв успешно обновлен']);
    }

    public function destroy(int $id, ReserveLinkManager $manager): JsonResponse
    {
        $reserve = Reserve::query()->with('child_links:reserve_id,parent_reserve_id')->find($id);

        if (!$reserve) {
            return response()->json(['status' => 1, 'message' => 'Резерв не найден'], 404);
        }

        if ($reserve->child_links->count() > 0) {
            return response()->json([
                'status' => 1,
                'message' => 'Нельзя удалить резерв: его используют другие резервы. Сначала отвяжите связанные резервы.',
            ], 422);
        }

        if ($reserve->reserve_manual_event()->exists()) {
            $reserve->reserve_manual_event()->delete();
        }

        $manager->detach((int) $reserve->id);
        $reserve->delete();

        return response()->json(['status' => 0, 'message' => 'Резерв успешно удален']);
    }

    public function unattach(int $id, ReserveLinkManager $linkManager): RedirectResponse
    {
        $reserve = Reserve::query()->find($id);

        if ($reserve) {
            $linkManager->detach((int) $reserve->id);
        }

        return redirect()->route('reserves.index');
    }

    private function event(array $data, string $type): void
    {
        $scale = 18;

        $from = (string) ($data['reserve_from'] ?? '0');
        $to = (string) ($data['reserve_to'] ?? '0');

        $fromDec = BigDecimal::of($from)->toScale($scale, RoundingMode::DOWN);
        $toDec = BigDecimal::of($to)->toScale($scale, RoundingMode::DOWN);

        ReserveManualEvent::query()->create([
            'id_user' => (int) auth()->id(),
            'id_reserve' => (int) $data['id_reserve'],
            'type_reserve' => ($type === 'minus' ? 0 : 1),
            'reserve_from' => $fromDec->__toString(),
            'reserve_to' => $toDec->__toString(),
            'comment' => $data['comment'] ?? null,
        ]);
    }
}
