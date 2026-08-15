<?php

namespace App\Http\Controllers\Administrator\Basic;

use App\Http\Controllers\Controller;
use App\Models\CurrencyFields;
use App\Models\DirectionExchange;
use App\Models\DirectionField;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DirectionFieldsController extends Controller
{
    /**
     * Список всех доп. полей
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $fields = DirectionField::orderBy('sorting')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'attributes' => [
                    'name' => $item->name,
                    'key_id' => $item->key_id,
                    'direction_exchanges' => $item->direction_exchange->count() > 0 ? $item->direction_exchange->map(function($item) {
                        return [
                            'id' => $item->id,
                            'name' => $item->tech_name
                        ];
                    }) : [],
                    'min_char' => $item->min_char,
                    'max_char' => $item->max_char,
                    'obligatory_field' => $item->obligatory_field,
                    'field_type' => $item->field_type,
                    'status' => (int)$item->status
                ]
            ];
        });

        return response()->json([
            'data' => $fields,
            'total' => count($fields)
        ]);
    }
    /**
     * Обработка и добавления нового поля
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        if($request->has('updateField'))
        {
            if($request->get('updateField') == 'status')
            {
                DirectionField::find((int)$request->id)->update([
                    'status' => (int)$request->status
                ]);

                return response()->json([
                    'status' => 0
                ]);
            }
        }

        $validator = Validator::make($request->all(), [
            'name.'.config('iexexchanger.default_locale') => 'required|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }


        // Генерация key_id на основе имени
        $keyId = Str::slug($request->input('name.' . config('iexexchanger.default_locale')), '_');

        $originalKeyId = $keyId;
        $counter = 1;
        while (DirectionField::where('key_id', $keyId)->exists()) {
            $keyId = $originalKeyId . '_' . $counter;
            $counter++;
        }

        $field = DirectionField::create([
            'name' => $request->name,
            'key_id' => $keyId
        ]);

        return response()->json([
            'status' => 0,
            'message' => $field->name .' успешно добавлен'
        ]);
    }

    /**
     * Форма, редактирование доп. полей
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(int $id, Request $request)
    {
        $item = DirectionField::find($id);

        if($request->has('is_loading_direction'))
        {
            $directionExchange = DirectionExchange::select('id', 'tech_name', 'status')->withCount('direction_field')->orderBy('direction_field_count', 'desc')
                ->lazy()->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'value' => $item->tech_name
                    ];
                });

            return response()->json([
                'items' => $directionExchange,
                'values' => $item->direction_exchange->pluck('id')
            ]);
        }

        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'name' => $item->getTranslations('name'),
                'description' => $item->getTranslations('description'),
                'type_field' => $item->type_field,
                'remove_spaces' => (string)$item->remove_spaces,
                'language_field' => $item->language_field,
                'start_with' => $item->start_with,
                'end_with' => $item->end_with,
                'key_id' => $item->key_id,
                'min_char' => $item->min_char,
                'max_char' => $item->max_char,
                'obligatory_field' => (string)$item->obligatory_field,
                'field_type' => (int)$item->field_type,
                'status' => (bool)$item->status
            ]
        ]);
    }

    /**
     * Обработка и обновления полей
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(int $id, Request $request)
    {
        $field = DirectionField::find($id);

        $validator = Validator::make($request->all(), [
            'name.'.config('iexexchanger.default_locale') => 'required|max:255',
            'field_type' => ['required']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        // Генерация key_id без учета типа (без income_/outcome_)
        $keyId = preg_replace('/[^a-z0-9_]/', '', Str::lower($request->key_id));

        if (DirectionField::where('key_id', $keyId)->where('id', '!=', $id)->exists()) {
            return response()->json([
                'status' => 1,
                'message' => 'Ключ ' . $keyId . ' уже существует и не может быть дублировано.'
            ]);
        }

        $options = [
            'name' => $request->name,
            'description' => $request->description,
            'key_id' => Str::lower($keyId),
            'min_char' => $request->has('min_char') ? $request->get('min_char') : 0,
            'max_char' => $request->has('max_char') ? $request->get('max_char') : 0,
            'obligatory_field' => $request->has('obligatory_field') ? $request->get('obligatory_field') : 0,
            'remove_spaces' => $request->has('remove_spaces') ? $request->get('remove_spaces') : 0,
            'field_type' => $request->has('field_type') ? $request->get('field_type') : 0,
            'language_field' => $request->has('language_field') ? $request->get('language_field') : 0,
            'start_with' => $request->has('start_with') ? $request->get('start_with') : '',
            'end_with' => $request->has('end_with') ? $request->get('end_with') : '',
            'status' => $request->has('status') ? $request->get('status') : 0
        ];

        $field->update($options);
        $field->direction_exchange()->sync($request->direction_exchange ?? []);

        return response()->json([
            'status' => 0,
            'message' => $field->name. ' успешно обновлен'
        ]);
    }

    /**
     * Удаление доп. полей для направлений
     */
    public function destroy(int $id)
    {
        $field = DirectionField::findOrFail($id);
        $oldItem = $field;
        $field->direction_exchange()->detach($id);
        $field->delete();

        return response()->json([
            'status' => 0,
            'message' => $oldItem->name. ' успешно удален'
        ]);
    }
}
