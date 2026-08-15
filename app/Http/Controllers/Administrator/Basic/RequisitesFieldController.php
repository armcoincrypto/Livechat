<?php

namespace App\Http\Controllers\Administrator\Basic;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\RequisiteField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RequisitesFieldController extends Controller
{
    /**
     * Список групп платежных реквизитов
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $fields = RequisiteField::orderBy('sorting')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'attributes' => [
                    'name' => $item->name,
                    'value' => $item->value,
                    'currencies' => $item->currencies->pluck('id'),
                    'status' => (bool)$item->status
                ]
            ];
        });
        $currencies = Currency::active()->pluck('tech_name', 'id')->map(function ($value, $id) {
            return [
                'id' => $id,
                'value' => $value
            ];
        })->values();


        return response()->json([
            'data' => $fields,
            'currencies' => $currencies,
            'total' => count($fields)
        ]);
    }


    /**
     * Обработка и добавления нового поля
     *
     * @param Request $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function store(Request $request): JsonResponse
    {
        if($request->has('updateField'))
        {
            if($request->get('updateField') == 'status')
            {
                RequisiteField::find((int)$request->id)->update([
                    'status' => (int)$request->status
                ]);

                return response()->json([
                    'status' => 0
                ]);
            }
        }

        $validator = Validator::make($request->all(), [
            'name.'.config('iexexchanger.default_locale') => 'required|max:255',
            'value' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $item = RequisiteField::create([
            'value' => $request->has('value') ? $request->get('value') : '',
            'name' => $request->name
        ]);


        return response()->json([
            'status' => 0,
            'message' => $item->name
        ]);
    }

    /**
     * Форма изменения группы
     *
     * @return JsonResponse
     */
    public function edit(int $id)
    {
        $item = RequisiteField::findOrFail($id);
        $currencies = Currency::active()->pluck('tech_name', 'id')->map(function ($value, $id) {
            return [
                'id' => $id,
                'value' => $value
            ];
        })->values();

        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'name' => $item->getTranslations('name'),
                'value' => $item->value,
                'prefix' => $item->prefix,
                'comment' => $item->getTranslations('comment'),
                'ids_currencies' => $item->currencies->pluck('id'),
                'status' => (bool)$item->status
            ],
            'currencies' => $currencies
        ]);
    }

    /**
     * Обработчик обновления поля
     *
     * @param Request $request
     * @param $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        $field = RequisiteField::findOrFail($id);

        if($request->has('is_update'))
        {
            if($request->has('ids_change_currencies')) {
                $field->currencies()->sync($request->ids_change_currencies ?? []);
            }

            return response()->json([
                'status' => 0,
                'message' => ''
            ]);
        }

        $validator = Validator::make($request->all(), [
            'name.'.config('iexexchanger.default_locale') => 'required|max:255',
            'value' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }


        $options = [
            'value' => ($request->has('value') ? $request->get('value') : ''),
            'prefix' => ($request->has('prefix') ? $request->get('prefix') : ''),
            'status' => ($request->has('status') ? $request->get('status') : 0),
            'name' => $request->name,
            'comment' => $request->comment,
        ];

        $field->update($options);
        $field->currencies()->sync($request->ids_currencies ?? []);

        return response()->json([
            'status' => 0,
            'message' => $field->name.' успешно обновлен'
        ]);
    }

    /**
     * Удалить поле
     *
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        $field = RequisiteField::findOrFail($id);
        $oldItem = $field;
        $field->currencies()->detach($id);
        $field->delete();

        return response()->json([
            'status' => 0,
            'message' => $oldItem->name.' удален'
        ]);
    }
}
