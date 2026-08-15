<?php

namespace App\Http\Controllers\Administrator\Basic;

use App\Http\Controllers\Controller;
use App\Models\CheckboxAgreement;
use App\Models\DirectionExchange;
use App\Models\SelectorFee;
use App\Settings\SelectorFeeConfig;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DirectionSelectorFeesController extends Controller
{
    /**
     * Получение списка Selector Fees и направлений для админки.
     */
    public function index(
        Request $request,
        SelectorFeeConfig $settings
    )
    {
        // Загрузка списка направлений обмена (оптимизировано)
        if ($request->has('is_loading_direction')) {
            return response()->json([
                'items' => DirectionExchange::select('id', 'tech_name')
                    ->lazy()
                    ->map(fn ($item) => ['id' => $item->id, 'value' => $item->tech_name])
            ]);
        }


        // Получаем список всех Selector Fees
        $items = SelectorFee::orderBy('sorting')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'attributes' => [
                    'name' => $item->name,
                    'description' => $item->description,
                    'fee' => $item->fee,
                    'fee_type' => $item->fee_type,
                    'status' => (bool)$item->status,
                    'sorting' => $item->sorting,
                    'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                    'updated_at' => $item->updated_at->translatedFormat('d M Y H:i'),
                ]
            ];
        });

        return response()->json([
            'data' => $items,
            'total' => $items->count(),
            'settings' => $settings->toArray(),
        ]);
    }

    /**
     * Создание новой опции чекбокса
     */
    public function store(
        Request $request,
        SelectorFeeConfig $settings
    )
    {
        // Если включена возможность обновления данных
        if($request->is_update == 1 and $request->has('showPage'))
        {
            // Обновление колонок
            if ((string)$request->showPage === 'settings') {
                $allowFilteredPage = [
                    'is_multi_selector',
                ];

                $update = [];
                foreach ($allowFilteredPage as $item) {
                    $update[$item] = $request->has($item)
                        ? (int) $request->get($item)
                        : 0;
                }

                $settings->update($update);
            }

            return response()->json([
                'status' => 0,
                'message' => __('Настройки успешно сохранены')
            ]);
        }


        if ($request->get('updateField') === 'status') {
            SelectorFee::findOrFail((int)$request->id)->update(['status' => (bool)$request->status]);
            return response()->json(['status' => 0]);
        }


        $validator = Validator::make($request->all(), [
            'name.' . config('iexexchanger.default_locale') => 'required|string|max:255',
            'fee' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $selectorFee = SelectorFee::create([
            'name' => $request->name,
            'description' => $request->description,
            'fee' => $request->fee,
            'fee_type' => 'dynamic',
            'status' => false,
            'sorting' => SelectorFee::max('sorting') + 1,
        ]);

        return response()->json([
            'status' => 0,
            'message' => $selectorFee->name .' ' .__('успешно добавлен'),
        ]);
    }

    /**
     * Получение данных чекбокса для редактирования.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(int $id)
    {
        $item = SelectorFee::with('excludedDirections')->findOrFail($id);
        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'locales' => $item->getTranslations(),
                'name' => $item->name,
                'description' => $item->description,
                'fee' => $item->fee,
                'fee_type' => $item->fee_type,
                'status' => (bool)$item->status,
                'sorting' => $item->sorting,
                'ids_excluded_directions' => $item->excludedDirections->pluck('id'),
            ]
        ]);
    }

    /**
     * Обновление чекбокса
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, int $id)
    {
        $selectorFee = SelectorFee::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name.' . config('iexexchanger.default_locale') => 'required|string|max:255',
            'fee' => 'required|string|max:50',
            'fee_type' => 'required|in:dynamic,profit',
            'ids_excluded_directions' => 'nullable|array',
            'ids_excluded_directions.*' => 'exists:direction_exchange,id',
            'description' => 'nullable|array',
            'description.*' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        // Normalize fee for profit type on update: store clean number without sign
        $normalizedFee = (string) ($request->fee ?? '');
        if ($request->fee_type === 'profit') {
            $normalizedFee = preg_replace('/^[+-]\s*/u', '', trim($normalizedFee));
        }

        $selectorFee->update([
            'name' => $request->name,
            'description' => $request->description,
            'fee' => $normalizedFee,
            'fee_type' => $request->fee_type,
            'status' => (bool)$request->status,
        ]);

        // Обновляем связи с исключёнными направлениями
        $selectorFee->excludedDirections()->sync($request->ids_excluded_directions ?? []);

        return response()->json([
            'status' => 0,
            'message' => $selectorFee->name . ' ' . __('успешно обновлен'),
        ]);
    }

    /**
     * Удаление чекбокса (с проверкой на защиту).
     *
     * @param int $id - идентификатор чекбокса для удаления
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id)
    {
        // Получаем чекбокс или выдаём ошибку, если не найден
        $selectorFee = SelectorFee::findOrFail($id);

        // Удаление связанных данных (исключённые направления обмена)
        $selectorFee->excludedDirections()->detach();

        // Удаление самого чекбокса
        $selectorFee->delete();

        return response()->json([
            'status' => 0,
            'message' => $selectorFee->name . ' ' . __('успешно удален'),
        ]);
    }
}
