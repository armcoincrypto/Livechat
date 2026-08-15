<?php

namespace App\Http\Controllers\Administrator\Bonuses;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Bonuses\DiscountResources;
use App\Models\RewardProgram;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DiscountController extends Controller
{
    /**
     * Список бонусных программ
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        if($request->has('showPage') == 'settings') {
            return response()->json([
                'is_discount_disabled' => (int)iEXSetting('is_discount_disabled', 0)
            ]);
        }

        $bonus = RewardProgram::filter($request->all());

        if(!$request->has('sorting_order')) {
            $bonus = $bonus->orderBy('id', 'desc');
        }
        $bonus = $bonus->paginate(iEXSetting('admin_bonuses_discount_pagination', 20));

        $table_selected_columns = explode(',', iEXSetting('admin_bonuses_discount_hidden_columns'));

        return response()->json([
            'items' => new DiscountResources($bonus),
            'selected_columns' => collect($table_selected_columns)->map(function ($item) {
                return $item;
            }),
            'per_page' => (int)iEXSetting('admin_bonuses_discount_pagination', 20)
        ]);
    }


    /**
     * Обработка и добавление новой программы
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Если включена возможность обновления данных
        if($request->is_update == 1 and $request->has('showPage'))
        {
            // Обновление колонок
            if($request->showPage == 'settings')
            {
                iEXSetting([
                    'is_discount_disabled' => $request->is_discount_disabled ?? 0
                ]);
            }

            return response()->json([
                'status' => 0,
                'message' => __('Настройки успешно сохранены')
            ]);
        }

        $validator = \Validator::make($request->all(), [
            'amount' => 'required|numeric',
            'percent' => 'required|numeric',
        ]);

        // Перед добавлением проверяем на ошибки
        if ($validator->fails()) {

            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }
            $options = [
                'name' => Str::lower(\Str::random(10)),
                'percent' => $request->get('percent'),
                'amount' => $request->get('amount'),
                'title' => $request->title,
                'description' => $request->description
            ];

            // Удаляем кэш
            cache()->forget('static-page-referral');
            $item = RewardProgram::create($options);


        return response()->json([
            'status' => 0,
            'message' => $item->title .' успешно добавлена'
        ]);
    }

    /**
     * Форма редактирования программы
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(int $id)
    {
        $item = RewardProgram::findOrFail($id);

        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'locales' => $item->getTranslations(),
                'name' => $item->name,
                'is_reg' => $item->is_reg,
                'amount' => $item->amount,
                'percent' => $item->percent,
                'sign' => $item->sign,
                'title' => $item->title,
                'description' => $item->description,
            ]
        ]);
    }

    /**
     * Обработка и обновление программы
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(int $id, Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'amount' => 'required|numeric',
            'percent' => 'required|numeric'
        ]);

        // Перед добавлением проверяем на ошибки
        if ($validator->fails()) {

            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $options = [
            'percent' => $request->get('percent'),
            'amount' => $request->get('amount'),
            'title' => $request->title,
            'description' => $request->description
        ];

        // Удаляем кэш
        cache()->forget('static-page-referral');
        $item = RewardProgram::findOrFail($id);
        $item->update($options);

        return response()->json([
            'status' => 0,
            'message' => $item->title .' успешно добавлена'
        ]);
    }

    /**
     * Удалить программу
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Exception
     */
    public function destroy(int $id)
    {
        $item = RewardProgram::findOrFail($id);
        $oldItem = $item;
        $exists = User::where('id_reward_program', '=', $id)->exists();

        if ($exists) {
            return response()->json([
                'status' => 1,
                'message' => $oldItem->title .' невозможно удалить к ней привязаны пользователи'
            ]);
        }

        $item->delete();
        return response()->json([
            'status' => 0,
            'message' => $oldItem->title .' успешно удален'
        ]);
    }
}
