<?php

namespace App\Http\Controllers\Administrator\Tools;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Tools\BlackListResources;
use App\Models\BlacklistOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

class BlackListController extends Controller
{
    /**
     * Доступные типы
     *
     * @var array
     */
    protected $types = [
        0 => 'счет',
        1 => 'email',
        2 => 'ip',
    ];

    public function index(
        Request $request)
    {
        if($request->has('showPage') == 'settings') {
            return response()->json([
                'is_local_blacklist' => (int)iEXSetting('is_local_blacklist', 0),
                'is_blacklist_method' => (int)iEXSetting('is_blacklist_method', 0)
            ]);
        }


        $lists = BlacklistOrder::filter($request->all())->orderByDesc('id')->paginate(20);

        // Удаляем ненужные ключи из массива
        if ($request->has('send_action')) {
            $conditions = Arr::except($request->all(), ['send_action']);

            return \redirect()->route('blacklist.index', $conditions);
        }

        return response()->json([
            'items' => new BlackListResources($lists)
        ]);
    }

    public function store(Request $request)
    {
        // Если включена возможность обновления данных
        if($request->is_update == 1 and $request->has('showPage'))
        {
            // Обновление колонок
            if($request->showPage == 'settings')
            {
                iEXSetting([
                    'is_local_blacklist' => $request->is_local_blacklist ?? 0,
                    'is_blacklist_method' => $request->is_blacklist_method ?? 0,
                ]);
            }

            return response()->json([
                'status' => 0,
                'message' => __('Настройки успешно сохранены')
            ]);
        }


        $validator = Validator::make($request->all(), [
            'value' => 'required|max:255',
            'text' => 'required|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }


        $uuid = \Str::uuid();
        BlacklistOrder::create([
            'hash_id' => $uuid,
            'type' => 3,
            'value' => ($request->has('value') ? security_xss($request->get('value')) : ''),
            'text' => ($request->has('text') ? security_xss($request->get('text')) : ''),
        ]);

        return response()->json([
            'status' => 0,
            'message' => 'Запись успешно добавлена'
        ]);
    }

    public function edit($id)
    {
        $item = BlacklistOrder::findOrFail($id);


        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'text' => $item->text,
                'value' => $item->value
            ]
        ]);
    }

    /**
     * @return JsonResponse
     */
    public function update(Request $request, $id)
    {
        $blacklist = BlacklistOrder::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'value' => 'required|max:255',
            'text' => 'required|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $blacklist->update([
            'value' => ($request->has('value') ? security_xss($request->get('value')) : ''),
            'text' => ($request->has('text') ? security_xss($request->get('text')) : ''),
        ]);

        return response()->json([
            'status' => 0,
            'message' => 'Запись успешно обновлена'
        ]);
    }

    /**
     * Удаление записи
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id)
    {
        $role = BlacklistOrder::findOrFail($id);
        $role->delete();


        return response()->json([
            'status' => 0,
            'message' => 'Запись успешно удалена'
        ]);
    }
}
