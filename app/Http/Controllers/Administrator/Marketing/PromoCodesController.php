<?php

namespace App\Http\Controllers\Administrator\Marketing;

use App\Http\Resources\Admin\Tools\PromoCodesResources;
use App\Models\DirectionExchange;
use App\Models\PromoCode;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class PromoCodesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        if($request->has('with') and $request->get('with') == 'direction_exchange') {
            $direction_exchange = DirectionExchange::select('id', 'tech_name')->active()->cursor();

            return response()->json($direction_exchange);
        }

        $promo_codes = PromoCode::orderByDesc('id')->paginate(20);
        return response()->json([
            'items' => new PromoCodesResources($promo_codes)
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:191'],
            'code' => ['required', 'string', 'max:191', 'regex:/^[A-Za-z0-9]+$/', 'unique:promo_codes,code'],

            // NULL = безлимит
            'count_uses' => ['nullable', 'integer', 'min:0'],

            'discount_type' => ['nullable', 'in:percent,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0'],

            'scope_mode' => ['nullable', 'in:all,include'],

            // Новые поля вместо timestamp_at
            'started_at' => ['nullable', 'date'],
            'expired_at' => ['nullable', 'date', 'after_or_equal:started_at'],

            // Направления
            'permitted_directions' => ['nullable', 'array'],
            'permitted_directions.*' => ['integer', 'min:1'],
            'forbidden_directions' => ['nullable', 'array'],
            'forbidden_directions.*' => ['integer', 'min:1'],

            // Статус (0/1)
            'status' => ['nullable'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first(),
            ]);
        }

        try {
            DB::beginTransaction();

            $promo = PromoCode::create([
                'id_manager' => (int) $request->user()->id,
                'name' => (string) $request->input('name'),

                'status' => $request->has('status') ? (int) (bool) $request->input('status') : 0,

                'started_at' => $request->input('started_at'),
                'expired_at' => $request->input('expired_at'),

                'code' => (string) $request->input('code'),

                'count_uses' => $request->filled('count_uses') ? (int) $request->input('count_uses') : null,
                'used' => 0,

                'discount_type' => $request->filled('discount_type') ? (string) $request->input('discount_type') : 'percent',
                'discount_value' => (string) $request->input('discount_value'),

                'scope_mode' => $request->filled('scope_mode') ? (string) $request->input('scope_mode') : 'all',
            ]);

            $promo->refresh();

            $includeIds = $this->normalizeDirectionIds($request->input('permitted_directions'));
            $excludeIds = $this->normalizeDirectionIds($request->input('forbidden_directions'));

            // Если scope_mode=include — exclude игнорируем
            if ((string) $promo->scope_mode === 'include') {
                $excludeIds = [];
            }

            $this->syncDirectionScopes($promo, $includeIds, $excludeIds);

            DB::commit();

            return response()->json([
                'status' => 0,
                'message' => 'Промокод успешно добавлен',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return response()->json([
                'status' => 1,
                'message' => 'Ошибка создания промокода',
            ]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(int $id)
    {
        $item = PromoCode::findOrFail($id);

        $includeIds = DB::table('promo_code_directions')
            ->where('promo_code_id', (int) $item->id)
            ->where('mode', 'include')
            ->pluck('direction_exchange_id')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();

        $excludeIds = DB::table('promo_code_directions')
            ->where('promo_code_id', (int) $item->id)
            ->where('mode', 'exclude')
            ->pluck('direction_exchange_id')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();

        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'name' => (string) $item->name,
                'status' => (bool) $item->status,
                'count_uses' => $item->count_uses === null ? null : (int) $item->count_uses,
                'discount_type' => (string) ($item->discount_type ?? 'percent'),
                'discount_value' => (string) ($item->discount_value ?? '0'),
                'scope_mode' => (string) ($item->scope_mode ?? 'all'),
                'permitted_directions' => $includeIds,
                'forbidden_directions' => $excludeIds,
                'code' => (string) $item->code,
                'started_at' => $item->started_at?->toDateString(),
                'expired_at' => $item->expired_at?->toDateString(),
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:191'],
            'code' => ['required', 'string', 'max:191', 'regex:/^[A-Za-z0-9]+$/', 'unique:promo_codes,code,' . $id],
            'count_uses' => ['nullable', 'integer', 'min:0'],
            'discount_type' => ['nullable', 'in:percent,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'scope_mode' => ['nullable', 'in:all,include'],
            // timestamp_at and its subfields removed
            'started_at' => ['nullable', 'date'],
            'expired_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'permitted_directions' => ['nullable', 'array'],
            'permitted_directions.*' => ['integer', 'min:1'],
            'forbidden_directions' => ['nullable', 'array'],
            'forbidden_directions.*' => ['integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $promo = PromoCode::findOrFail($id);
        $promo->update([
            'name' => (string) $request->name,
            'status' => $request->has('status') ? (int) (bool) $request->get('status') : 0,
            'started_at' => $request->input('started_at'),
            'expired_at' => $request->input('expired_at'),
            'code' => (string) $request->get('code'),
            'count_uses' => $request->filled('count_uses') ? (int) $request->get('count_uses') : null,
            'discount_type' => $request->filled('discount_type') ? (string) $request->get('discount_type') : 'percent',
            'discount_value' => (string) $request->get('discount_value'),
            'scope_mode' => $request->filled('scope_mode') ? (string) $request->get('scope_mode') : 'all',
        ]);
        $promo->refresh();

        $includeIds = $this->normalizeDirectionIds($request->input('permitted_directions'));
        $excludeIds = $this->normalizeDirectionIds($request->input('forbidden_directions'));

        if ((string) $promo->scope_mode === 'include') {
            $excludeIds = [];
        }

        $this->syncDirectionScopes($promo, $includeIds, $excludeIds);

        return response()->json([
            'status' => 0,
            'message' => ($promo->name . ' успешно обновлен')
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id)
    {
        PromoCode::findOrFail($id)->update([
            'status' => 2,
        ]);


        return response()->json([
            'status' => 0,
            'message' => 'Промо-код отключен'
        ]);
    }
    /**
     * Normalize an array of direction IDs (from request) to an array of positive integers.
     *
     * @param mixed $value
     * @return array
     */
    private function normalizeDirectionIds(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $v) {
            $v = is_string($v) ? trim($v) : $v;
            if ($v === '' || $v === null) {
                continue;
            }
            if (is_numeric($v)) {
                $ids[] = (int) $v;
            }
        }

        $ids = array_values(array_unique(array_filter($ids, static fn (int $id) => $id > 0)));
        return $ids;
    }

    /**
     * Sync direction scopes (include/exclude) for a promo code.
     *
     * @param PromoCode $promo
     * @param array $includeIds
     * @param array $excludeIds
     * @return void
     */
    private function syncDirectionScopes(PromoCode $promo, array $includeIds, array $excludeIds): void
    {
        DB::table('promo_code_directions')->where('promo_code_id', (int) $promo->id)->delete();

        $now = now();
        $rows = [];

        foreach ($includeIds as $id) {
            $rows[] = [
                'promo_code_id' => (int) $promo->id,
                'direction_exchange_id' => (int) $id,
                'mode' => 'include',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach ($excludeIds as $id) {
            $rows[] = [
                'promo_code_id' => (int) $promo->id,
                'direction_exchange_id' => (int) $id,
                'mode' => 'exclude',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows) {
            DB::table('promo_code_directions')->insert($rows);
        }
    }


    public function usageSummary(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $limit = (int) $request->input('limit', 50);
        $limit = max(1, min($limit, 200));

        $promo = PromoCode::query()->findOrFail($id);

        // Последние N заявок по промокоду (с названием направления)
        $tasks = Task::query()
            ->select([
                'id',
                'public_id',
                'status',
                'created_at',
                'id_direction_exchange',
                'promo_code_code',
                'promo_code_discount_type',
                'promo_code_value',
                'receiving_price_with_promocode',
            ])
            ->where('id_promo_code', (int) $promo->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->with([
                'direction_exchange:id,tech_name',
                'task_status:id,name',
            ])
            ->get()
            ->map(fn (Task $t) => [
                'id' => (int) $t->id,
                'public_id' => (int) ($t->public_id ?? 0),
                'status' => [
                    'id' => (int) $t->status,
                    'name' => (string) ($t->task_status?->name ?? ''),
                ],
                'created_at' => $t->created_at?->toDateTimeString(),

                'direction' => [
                    'id' => (int) $t->id_direction_exchange,
                    'tech_name' => (string) ($t->direction_exchange?->tech_name ?? ''),
                ],

                // промо-снапшот из tasks
                'promo_code_code' => $t->promo_code_code,
                'promo_code_discount_type' => $t->promo_code_discount_type,
                'promo_code_value' => $t->promo_code_value,
                'promo_bonus' => $t->receiving_price_with_promocode,
            ])
            ->values();

        // Направления, где промокод фактически применялся (берём из этих задач)
        $usedDirectionsMap = [];
        foreach ($tasks as $t) {
            $dir = $t['direction'] ?? null;
            $dirId = is_array($dir) ? (int) ($dir['id'] ?? 0) : 0;
            if ($dirId <= 0) {
                continue;
            }
            $usedDirectionsMap[$dirId] = $dir;
        }
        $usedDirections = array_values($usedDirectionsMap);

        return response()->json([
            'status' => 0,
            'data' => [
                'promo' => [
                    'id' => (int) $promo->id,
                    'name' => (string) $promo->name,
                    'code' => (string) $promo->code,
                    'discount_type' => (string) ($promo->discount_type ?? 'percent'),
                    'discount_value' => (string) ($promo->discount_value ?? '0'),
                    'scope_mode' => (string) ($promo->scope_mode ?? 'all'),
                    'status' => (int) $promo->status,
                ],

                // направления, где промокод реально применялся
                'used_directions' => $usedDirections,

                // единый список заявок (последние N)
                'tasks' => $tasks,
            ],
        ]);
    }
}
