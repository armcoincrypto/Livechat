<?php

namespace App\Http\Controllers\Administrator\Basic;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\CurrencyFields;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CurrencyFieldsController extends Controller
{
    /**
     * Список дополнительных полей
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $fields_in = CurrencyFields::where('when_print', '=', 0)->orderBy('sorting')->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'attributes' => [
                        'name' => $item->name,
                        'key_id' => $item->key_id,
                        'currencies' => $item->currencies_in->count() > 0 ? $item->currencies_in->map(function($item) {
                            return [
                                'id' => $item->id,
                                'name' => $item->tech_name
                            ];
                        }) : [],
                        'min_char' => $item->min_char,
                        'max_char' => $item->max_char,
                        'obligatory_field' => $item->obligatory_field,
                        'field_type' => $item->field_type,
                        'status' => (int)$item->status == 0
                    ]
                ];
            });
        $fields_out = CurrencyFields::where('when_print', '=', 1)->orderBy('sorting_out')->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'attributes' => [
                        'name' => $item->name,
                        'key_id' => $item->key_id,
                        'currencies' => $item->currencies_out->count() > 0 ? $item->currencies_out->map(function($item) {
                            return [
                                'id' => $item->id,
                                'name' => $item->tech_name
                            ];
                        }) : [],
                        'min_char' => $item->min_char,
                        'max_char' => $item->max_char,
                        'obligatory_field' => $item->obligatory_field,
                        'field_type' => $item->field_type,
                        'status' => (int)$item->status == 0
                    ]
                ];
            });


        return response()->json([
            'data' => [
                'fieldsIn' => $fields_in,
                'fieldsOut' => $fields_out,
            ],

            'totalIn' => count($fields_in),
            'totalOut' => count($fields_out),
        ]);
    }

    /**
     * Обработка и добавление полей + настройки
     *
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        if($request->has('updateField'))
        {
            if($request->get('updateField') == 'status')
            {
                CurrencyFields::find((int)$request->id)->update([
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
        $nameSlug = Str::slug($request->input('name.' . config('iexexchanger.default_locale')), '_');

        if (Str::contains(mb_strtolower($nameSlug), ['fio', 'фио'])) {
            $keyId = $request->when_print == 0 ? 'sender_fullname' : 'recipient_fullname';
        } else {
            $prefix = $request->when_print == 0 ? 'income_' : 'outcome_';
            $keyId = $prefix . $nameSlug;
        }

        $originalKeyId = $keyId;
        $counter = 1;
        while (CurrencyFields::where('key_id', $keyId)->exists()) {
            $keyId = $originalKeyId . '_' . $counter;
            $counter++;
        }

        $options = [
            'when_print' => $request->has('when_print') ? $request->get('when_print') : 0,
            'name' => $request->name,
            'status' => 1,
            'key_id' => $keyId
        ];

        $field = CurrencyFields::create($options);

        return response()->json([
            'status' => 0,
            'message' => $field->name .' успешно добавлен'
        ]);
    }

    /**
     * Форма редактирование доп. полей для валют
     *
     * @return JsonResponse
     */
    public function edit(int $id)
    {
        $item = CurrencyFields::findOrFail($id);

        $currencies = Currency::active()->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'value' => $item->tech_name,
            ];
        })->values();

        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'name' => $item->getTranslations('name'),
                'when_print' => (string)$item->when_print,
                'type_field' => $item->type_field,
                'example' => $item->getTranslations('example'),
                'description_field' => $item->getTranslations('description_field'),
                'list_text' => $item->getTranslations('list_text'),
                'validator_type' => $item->validator_type ?? '',
                'remove_spaces' => (string)$item->remove_spaces,
                'language_field' => $item->language_field,
                'start_with' => $item->start_with,
                'end_with' => $item->end_with,
                'key_id' => $item->key_id,
                'currencies_in' => $item->currencies_in->pluck('id'),
                'currencies_out' => $item->currencies_out->pluck('id'),
                'min_char' => $item->min_char,
                'max_char' => $item->max_char,
                'obligatory_field' => (string)$item->obligatory_field,
                'field_type' => (string)$item->field_type,
                'status' => (int)$item->status
            ],
            'currencies' => $currencies
        ]);
    }

    /**
     * Обработка и обновление данных
     */
    public function update(int $id, Request $request)
    {
        $field = CurrencyFields::findOrFail($id);
        $validator = Validator::make($request->all(), [
            'key_id' => 'required',
            'name.'.config('iexexchanger.default_locale') => 'required|max:255',
            'field_type' => ['required']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $whenPrint = (int)$request->when_print;
        $keyId = preg_replace('/[^a-z0-9_]/', '', Str::lower($request->key_id));

        if (CurrencyFields::where('key_id', $keyId)->where('id', '!=', $id)->exists()) {
            return response()->json([
                'status' => 1,
                'message' => 'Ключ ' . $keyId . ' уже существует и не может быть дублировано.'
            ]);
        }

        if ($whenPrint === 0 && !in_array($keyId, ['sender_fullname', 'recipient_fullname'])) {
            if (!str_starts_with($keyId, 'income_')) {
                $keyId = 'income_' . $keyId;
            }
        } elseif ($whenPrint === 1 && !in_array($keyId, ['sender_fullname', 'recipient_fullname'])) {
            if (!str_starts_with($keyId, 'outcome_')) {
                $keyId = 'outcome_' . $keyId;
            }
        }

        $options = [
            'name' => $request->name,
            'list_text' => $request->list_text,
            'validator_type' => $request->validator_type,
            'description_field' => $request->description_field,
            'example' => $request->example,
            'key_id' => Str::lower($keyId),
            'when_print' => $whenPrint,
            'type_field' => $request->type_field,

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
        $field->currencies_in()->sync($request->currencies_in ?? []);
        $field->currencies_out()->sync($request->currencies_out ?? []);


        return response()->json([
            'status' => 0,
            'message' => $field->name. ' успешно обновлен'
        ]);
    }

    /**
     * Удаляем доп. поле
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $field = CurrencyFields::findOrFail($id);
        $oldItem = $field;
        $field->currencies_in()->detach($field->id);
        $field->currencies_out()->detach($field->id);
        $field->delete();

        return response()->json([
            'status' => 0,
            'message' => $oldItem->name. ' успешно удален'
        ]);
    }
}
