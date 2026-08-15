<?php

namespace App\Http\Controllers\Administrator\Crypto;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ParserRates\FileParserResources;
use App\Models\FileParserGroup;
use App\Models\FileParserRates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class FileParserController extends Controller
{
    /**
     * Главная страница
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $links = FileParserRates::with(['direction_exchange' => function ($q) {
            $q->select('id', 'tech_name', 'status', 'id_file_parser_rate');
        }])->filter($request->all())->paginate((int)iEXSetting('admin_file_parser_pagination', 20));

        $fileGroups = FileParserGroup::orderBy('sorting')->get()->map(function($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'link' => $item->link
                ];
            });

        $admin_hidden_columns = explode(',', iEXSetting('admin_file_parser_hidden_columns'));
        $allowedColumns = ['name', 'id_group', 'course', 'direction_exchange', 'created_at', 'last_updated', 'status'];

        return response()->json([
            'items' => new FileParserResources($links),
            'fileGroups' => $fileGroups,
            'selected_columns' => collect($admin_hidden_columns)->map(function ($item) {
                return $item;
            })->reject(fn($item) => !in_array($item, $allowedColumns))->values(),
            'per_page' => (int)iEXSetting('admin_file_parser_pagination', 20),
        ]);
    }

    /**
     * Обработка и добавление курсов конкурентов
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function store(Request $request)
    {
        if($request->has('updateField'))
        {
            if($request->get('updateField') == 'status')
            {
                FileParserRates::find((int)$request->id)->update([
                    'status' => (int)$request->status
                ]);

                return response()->json([
                    'status' => 0
                ]);
            }
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required'],
            'id_group' => ['required', 'exists:file_parser_groups,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $explode = explode('-', $request->get('name'));

        try {
            $rate = FileParserRates::create([
                'name' => $request->has('name') ? $request->get('name') : null,
                'exchange_in' => $explode[0],
                'exchange_out' => $explode[1],
                'id_group' => $request->has('id_group') ? $request->get('id_group') : 0,
                'status' => $request->has('status') ? $request->get('status') : 0,
                'number_format' => $request->has('number_format') ? $request->get('number_format') : 0,
                'type' => $request->has('type') ? $request->get('type') : 0,
            ]);
        }catch (\Exception $e) {
            return response()->json([
                'status' => 1,
                'message' => 'Ошибка добавления'
            ]);
        }

        $rate->update([
            'code' => '[fileparser_'.\Str::lower(Str::studly($rate->file_parser_group->name)).'_'.\Str::lower($rate->exchange_in.'-'.$rate->exchange_out).']',
        ]);

        return response()->json([
            'status' => 0,
            'message' => $rate->name .' '.__('успешно добавлен')
        ]);
    }

    /**
     * Форма редактирования курсов конкурентов
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(int $id)
    {
        $item = FileParserRates::findOrFail($id);

        $fileGroups = FileParserGroup::where('status', '=', 1)
            ->orderBy('sorting')->get()->map(function($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'link' => $item->link,
                    'status' => (bool)$item->status
                ];
            });


        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'name' => $item->name,
                'status' => (bool)$item->status,
                'exchange_in' => $item->exchange_in,
                'exchange_out' => $item->exchange_out,
                'id_group' => $item->id_group,
                'value' => $item->value,
                'summa' => $item->summa,
                'group' => [
                    'id' => $item->file_parser_group?->id,
                    'name' => $item->file_parser_group?->name,
                ],
                'number_format' => $item->number_format,
            ],

            'fileGroups' => $fileGroups,
        ]);
    }

    /**
     * Обработка и обновление курсов конкурентов
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $rates = FileParserRates::findOrFail($id);
        $validator = Validator::make($request->all(), [
            'name' => ['required'],
            'id_group' => ['required', 'exists:file_parser_groups,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $explode = explode('-', $request->get('name'));

        try {
            $rates->update([
                'name' => $request->has('name') ? $request->get('name') : null,
                'exchange_in' => $explode[0],
                'exchange_out' => $explode[1],
                'id_group' => $request->has('id_group') ? $request->get('id_group') : 0,
                'status' => $request->has('status') ? $request->get('status') : 0,
                'number_format' => $request->has('number_format') ? $request->get('number_format') : 0,
                'type' => $request->has('type') ? $request->get('type') : 0,
            ]);

            $rates->update([
                'code' => '[fileparser_'.\Str::lower(Str::studly($rates->file_parser_group->name)).'_'.\Str::lower($rates->exchange_in.'-'.$rates->exchange_out).']',
            ]);
        }catch (\Exception $e) {
            return response()->json([
                'status' => 1,
                'message' => 'Ошибка обновления'
            ]);
        }

        return response()->json([
            'status' => 0,
            'message' => $rates->name .' '.__('успешно обновлен')
        ]);
    }

    /**
     * Удаление курсов
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Exception
     */
    public function destroy(int $id): \Illuminate\Http\JsonResponse
    {
        $rate = FileParserRates::findOrFail($id);
        $oldItem = $rate;
        if ($rate->direction_exchange->count() > 0) {
           return response()->json([
               'status' => 1,
               'message' => "Выбранный курс '.$oldItem->name.' удалить невозможно, К нему привязаны направления"
           ]);
        }

        $rate->delete();

        return response()->json([
            'status' => 0,
            'message' => "{$oldItem->name} успешно удален"
        ]);
    }
}
