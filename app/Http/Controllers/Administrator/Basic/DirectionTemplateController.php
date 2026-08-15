<?php

namespace App\Http\Controllers\Administrator\Basic;

use App\Http\Controllers\Controller;
use App\Models\DirectionTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DirectionTemplateController extends Controller
{
    protected array $templates = [];

    public function __construct()
    {
        $this->templates = [
            0 => 'Инструкция по оплате',
            1 => 'Описание обмена',
            2 => 'Дополнительное описание обмена',
            3 => 'Формальное описание перед вводом данных',
            4 => 'Дополнительный текст в процессе оплаты (внизу)',
            5 => 'Текст для статуса "Заявка выполнена"',
            6 => 'Текст для статуса "Заявка отклонена"',
        ];
    }

    public function index()
    {
        $templates = DirectionTemplate::orderBy('id', 'desc')->get();

        return response()->json([
            'data' => $templates->map(function ($item) {
                return [
                    'id' => $item->id,
                    'attributes' => [
                        'name' => $this->templates[$item->id_type] ?? '',
                        'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                        'created_at_human' => $item->created_at->diffForHumans(),
                        'updated_at' => $item->updated_at->translatedFormat('d M Y H:i'),
                        'updated_at_human' => $item->updated_at->diffForHumans(),
                    ]
                ];
            })
        ]);
    }

    /**
     * Добавление нового шаблона
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_type_template' => 'required',
        ]);

        // Перед добавлением новой валюты проверяем на ошибки
        if ($validator->fails())
        {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $find = DirectionTemplate::where([
            ['id_type', '=', $request->get('id_type_template')],
        ]);

        if ($find->exists())
        {
            return response()->json([
                'status' => 1,
                'message' => __('Такой шаблон уже существует')
            ]);
        }

        DirectionTemplate::create([
            'id_type' => ($request->has('id_type_template') ? $request->get('id_type_template') : 0),
        ]);

        return response()->json([
            'status' => 0,
            'message' => __('Шаблон успешно добавлен')
        ]);
    }

    /**
     * Форма редактирования
     */
    public function edit(int $id, Request $request)
    {
        $item = DirectionTemplate::find($id);

        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'type_view_info' => $item->type_view_info,
                'text' => $item->getTranslations('text')
            ]
        ]);
    }

    public function update(int $id, Request $request)
    {
        $template = DirectionTemplate::find($id);
        $template->update([
            'type_view_info' => $request->has('type_view_info') ? $request->get('type_view_info') : 0,
            'text' => $request->text
        ]);

        return response()->json([
            'status' => 0,
            'message' => 'Данные успешно сохранены'
        ]);
    }

    public function destroy(int $id)
    {
        DirectionTemplate::find($id)->delete();

        return response()->json([
            'status' => 0,
            'message' => 'Шаблон успешно удален'
        ]);
    }
}
