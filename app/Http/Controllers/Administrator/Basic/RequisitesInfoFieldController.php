<?php

namespace App\Http\Controllers\Administrator\Basic;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\RequisiteInfoField;
use App\Models\Requisites;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RequisitesInfoFieldController extends Controller
{
    /**
     * Фильтры
     *
     * @var array
    */
    protected array $allowFilteredPage = [
        'admin_requisites_info_pagination',
        'admin_requisites_info_hidden_columns',
    ];

    /**
     * Список информационных полей
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $fields = RequisiteInfoField::orderBy('sorting')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'attributes' => [
                    'key_name' => $item->key_name,
                    'value_name' => $item->value_name,
                    'requisites' => $item->requisites->map(function($item) {
                        return [
                            'id' => $item->id,
                            'name' => $item->name,
                            'account_number' => $item->account_number
                        ];
                    }),
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
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {

        if($request->has('updateField'))
        {
            if($request->get('updateField') == 'status')
            {
                RequisiteInfoField::find((int)$request->id)->update([
                    'status' => (int)$request->status
                ]);

                return response()->json([
                    'status' => 0
                ]);
            }
        }


        $validator = Validator::make($request->all(), [
            'key_name.'.config('iexexchanger.default_locale') => 'required|max:255',
            'value_name.'.config('iexexchanger.default_locale') => 'required|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $options = [
            'status' => ($request->has('status') ? $request->get('status') : 0),
            'user_id' => $request->user()->id,
            'key_name' => $request->key_name,
            'value_name' => $request->value_name
        ];

        $item = RequisiteInfoField::create($options);

        return response()->json([
            'status' => 0,
            'message' => $item->key_name.' успешно добавлен'
        ]);
    }

    /**
     * Форма изменения группы
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(int $id)
    {
        $item = RequisiteInfoField::findOrFail($id);
        $requisites = Requisites::isNotHistory()->get()->map(function($item) {
            $stringActive = ($item->status == 0 ? 'не активен' : 'активен');
            return [
                'id' => $item->id,
                'value' => $item->currency->tech_name. ' ['. $item->account_number.'] '. '('. $stringActive .')'
            ];
        });

        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'key_name' => $item->getTranslations('key_name'),
                'value_name' => $item->getTranslations('value_name'),
                'ids_requisites' => $item->requisites->pluck('id'),
                'status' => (bool)$item->status
            ],
            'requisites' => $requisites
        ]);
    }

    /**
     * Редактирование информационных полей
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $field = RequisiteInfoField::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'key_name' => 'required',
            'value_name' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $options = [
            'status' => ($request->has('status') ? $request->get('status') : 0),
            'user_id' => $request->user()->id,
            'key_name' => $request->key_name,
            'value_name' => $request->value_name
        ];


        $field->update($options);
        $field->requisites()->sync($request->ids_requisites ?? []);

        return response()->json([
            'status' => 0,
            'message' => $field->key_name.' успешно обновлен'
        ]);

    }

    /**
     * Удалить поле и привязанные реквизиты
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): \Illuminate\Http\JsonResponse
    {
        $field = RequisiteInfoField::findOrFail($id);
        $oldItem = $field;
        $field->requisites()->detach($id);
        $field->delete();

        return response()->json([
            'status' => 0,
            'message' => "{$oldItem->key_name} успешно удален"
        ]);
    }
}
