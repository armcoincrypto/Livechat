<?php

namespace App\Http\Controllers\Administrator\Basic;

use App\Http\Controllers\Controller;
use App\Models\DirectionExchange;
use App\Models\DirectionExchangeGroup;
use App\Models\DirectionExchangeMode;
use App\Models\GroupCommission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DirectionExchangeGroupsController extends Controller
{
    /**
     * Список групп направлений для режимов
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // Отдельный метод загрузки направлений обмена (для оптимизации)
        if ($request->has('is_loading_direction')) {
            $onlyLinked = $request->boolean('only_linked');
            $groupId    = (int) $request->integer('group_id', 0);

            // Вариант с навигацией/pagination: ?is_loading_direction=1&with_nav=1&q=...&per_page=50&page=1
            if ($request->boolean('with_nav')) {
                $q = trim((string) $request->get('q', ''));
                $perPage = (int) $request->integer('per_page', 50);
                $perPage = ($perPage > 0 && $perPage <= 200) ? $perPage : 50;
                $page = (int) $request->integer('page', 1);

                $query = DirectionExchange::query()
                    ->from('direction_exchange') // ensure base table alias is consistent
                    ->select('direction_exchange.id', 'direction_exchange.tech_name')
                    ->when($onlyLinked && $groupId > 0, function ($qb) use ($groupId) {
                        $qb->join('direction_exchange_group_pivot as degp', 'degp.direction_exchange_id', '=', 'direction_exchange.id')
                            ->where('degp.group_id', $groupId);
                    })
                    ->when($q !== '', function ($qb) use ($q) {
                        $like = '%' . str_replace(['%','_'], ['\\%','\\_'], $q) . '%';
                        $qb->where('direction_exchange.tech_name', 'like', $like);
                    })
                    ->orderBy('direction_exchange.id')
                    ->distinct();

                $paginator = $query->paginate($perPage, ['*'], 'page', $page);

                return response()->json([
                    'items' => collect($paginator->items())->map(fn ($item) => [
                        'id' => $item->id,
                        'value' => $item->tech_name,
                    ])->values(),
                    'meta' => [
                        'current_page' => $paginator->currentPage(),
                        'per_page' => $paginator->perPage(),
                        'total' => $paginator->total(),
                        'last_page' => $paginator->lastPage(),
                        'has_more' => $paginator->hasMorePages(),
                    ],
                ]);
            }

            // Быстрый стрим без навигации (как раньше)
            return response()->json([
                'items' => DirectionExchange::query()
                    ->from('direction_exchange')
                    ->select('direction_exchange.id', 'direction_exchange.tech_name')
                    ->orderBy('direction_exchange.id')
                    ->distinct()
                    ->lazy()
                    ->map(fn ($item) => ['id' => $item->id, 'value' => $item->tech_name])
            ]);
        }

        // Отдельный метод загрузки направлений обмена (для оптимизации)
        if ($request->has('is_loading_group_commission')) {
            $group_commission = GroupCommission::orderByDesc('id')->get()->map(function($item) {
                return [
                    'id' => $item->id,
                    'value' => $item->name . ' (Комиссия: '. $item->receiving.')'
                ];
            });

            return response()->json($group_commission);
        }

        $modes = DirectionExchangeGroup::orderBy('sorting')
            ->withCount('directions')->get()->map(function ($value) {
            return [
                'id' => $value->id,
                'attributes' => [
                    'name' => $value->name,
                    'status' => (bool)$value->status,
                    'count' => $value->directions_count
                ]
            ];
        })->values();

        return response()->json([
            'data' => $modes,
            'total' => count($modes)
        ]);
    }

    /**
     * Обработка и добавления нового режима
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $item = DirectionExchangeGroup::create([
            'name' => $request->name
        ]);

        return response()->json([
            'status' => 0,
            'message' => $item->name . ' успешно добавлен'
        ]);
    }

    /**
     * Форма изменения режима
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(int $id, Request $request)
    {
        $item = DirectionExchangeGroup::findOrFail($id);

        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'name' => $item->name,
                'ids_allowed_directions' => $item->directions->pluck('id'),
            ]
        ]);
    }

    /**
     * Обработчик обновления режима
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $group = DirectionExchangeGroup::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'ids_allowed_directions' => 'nullable|array',
            'ids_allowed_directions.*' => 'exists:direction_exchange,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $group->update([
            'name' => ($request->has('name') ? $request->get('name') : null),
        ]);
        // Обновляем связи с исключёнными направлениями
        $group->directions()->sync($request->ids_allowed_directions ?? []);


        return response()->json([
            'status' => 0,
            'message' => $group->name . ' успешно обновлен'
        ]);
    }

    /**
     * Привязка/отвязка направлений к группе (M2M).
     * Параметры:
     * - direction_ids: int[] (обязательный)
     * - mode: attach|detach|sync (по умолчанию sync)
     */
    public function syncDirections(int $id, Request $request)
    {
        $group = DirectionExchangeGroup::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'direction_ids' => ['required', 'array'],
            'direction_ids.*' => ['integer', 'min:1'],
            'mode' => ['nullable', 'in:attach,detach,sync'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ], 422);
        }

        $ids = array_values(array_unique($request->input('direction_ids', [])));
        $mode = $request->input('mode', 'sync');

        switch ($mode) {
            case 'attach':
                $group->directions()->syncWithoutDetaching($ids);
                break;
            case 'detach':
                $group->directions()->detach($ids);
                break;
            default:
                $group->directions()->sync($ids);
        }

        return response()->json([
            'status' => 0,
            'message' => 'Связи успешно обновлены',
            'attached_count' => $group->directions()->count(),
        ]);
    }

    /**
     * Массовое сохранение значений для всех направлений группы.
     * Принимает:
     * - template: string (обязательный) — один из:
     *   profit, commissions-group, partner-rewards, partner-profit,
     *   либо одно из текстовых полей: deadline,instructions,desc_exchange,desc_exchange_dop,formalization_text,
     *   other_docs,notice_process_desc,text_order_success,text_order_failed,text_order_confirm,
     *   order_button_i_pay,order_button_i_pay_text,order_button_i_confirm,text_order_created_email
     * - details: mixed (обязательный) — набор значений для применения
     */
    public function updateGroupMassEditor(int $id, Request $request)
    {
        $group = DirectionExchangeGroup::findOrFail($id);

        $allowedTextFields = [
            'deadline','instructions','desc_exchange','desc_exchange_dop','formalization_text',
            'other_docs','notice_process_desc','text_order_success','text_order_failed','text_order_confirm',
            'order_button_i_pay','order_button_i_pay_text','order_button_i_confirm','text_order_created_email',
        ];

        $validator = Validator::make($request->all(), [
            'template' => ['required', 'string', Rule::in(array_merge([
                'profit','commissions-group','partner-rewards','partner-profit','status',
            ], $allowedTextFields))],
            'details'  => ['required'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ], 422);
        }

        $template = (string) $request->input('template');
        $details  = $request->input('details', []);

        // ID всех направлений группы
        $directionIds = $group->directions()->pluck('direction_exchange.id')->values();
        if ($directionIds->isEmpty()) {
            return response()->json([
                'status' => 1,
                'message' => 'В группе нет направлений для обновления',
            ], 422);
        }


        try {
            DB::beginTransaction();

            switch ($template) {

                case 'status': {
                    // 0 - неактивно, 1 - активно
                    $status = (int) ($details['status'] ?? 1);

                    DirectionExchange::whereIn('id', $directionIds)->update([
                        'status' => $status,
                    ]);

                    break;
                }

                case 'profit': {
                    $profit = (float) ($details['profit'] ?? 0);

                    DirectionExchange::whereIn('id', $directionIds)->update([
                        'profit' => $profit,
                    ]);
                    break;
                }
                case 'partner-profit': {
                    $profitPartner = (float) ($details['profit_partner'] ?? 0);
                    DirectionExchange::whereIn('id', $directionIds)->update([
                        'profit_partner' => $profitPartner,
                    ]);
                    break;
                }
                case 'partner-rewards': {
                    $isNotPartner = (int) ($details['is_not_partner'] ?? 0);
                    DirectionExchange::whereIn('id', $directionIds)->update([
                        'is_not_partner' => $isNotPartner,
                    ]);
                    break;
                }
                case 'commissions-group': {
                    // Ожидается массив ID комиссий (int[])
                    $ids = $details['ids_group_commissions'] ?? [];
                    $ids = is_array($ids) ? array_values(array_unique(array_map('intval', $ids))) : [];

                    // Выполняем sync() для каждой записи DirectionExchange в группе.
                    // Используем chunkById, чтобы не грузить всё в память.
                    DirectionExchange::whereIn('id', $directionIds)
                        ->chunkById(500, function ($chunk) use ($ids) {
                            foreach ($chunk as $direction) {
                                $direction->groupCommissions()->sync($ids);
                            }
                        });

                    break;
                }
                default: {
                    // Текстовые / многоязычные поля (Spatie Translatable поддержит массив значений)
                    if (!in_array($template, $allowedTextFields, true)) {
                        throw new \InvalidArgumentException('Недопустимое поле: '.$template);
                    }
                    $value = $details[$template] ?? (is_array($details) ? $details : null);
                    DirectionExchange::whereIn('id', $directionIds)->update([
                        $template => $value,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 0,
                'message' => 'Данные успешно сохранены',
                'updated_count' => $directionIds->count(),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => 1,
                'message' => 'Ошибка сохранения: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Удалить режим
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Exception
     */
    public function destroy(int $id)
    {
        $group = DirectionExchangeGroup::findOrFail($id);

        try {
            DB::beginTransaction();

            $name = $group->name;
            $group->directions()->detach();

            // Удалить саму группу
            $group->delete();

            DB::commit();

            return response()->json([
                'status'  => 0,
                'message' => $name . ' успешно удалена',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status'  => 1,
                'message' => 'Ошибка удаления: ' . $e->getMessage(),
            ], 500);
        }
    }
}
