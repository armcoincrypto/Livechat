<?php

namespace App\Http\Controllers\Administrator\Crypto;

use App\Http\Controllers\Controller;
use App\Models\ParserApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class APIKeyController extends Controller
{
    /**
     * Список API Ключей
     */
    public function index()
    {
        $items = ParserApiKey::orderByDesc('id')->get()->map(function($item) {
            return [
                'id' => $item->id,
                'attributes' => [
                    'api_key' => $item->api_key,
                    'provider_id' => $item->provider_id,
                    'view_count' => iex_number_format($item->view_count, 0, true),
                    'status' => (bool)$item->status
                ]
            ];
        });

        return response()->json([
            'data' => $items,
            'total' => count($items),
        ]);
    }

    /**
     * Обработка и добавление нового ключа
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'keys' => ['required'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }
        $array = explode(',', remove_all_spaces($request->get('keys')));

        foreach ($array as $item) {
            ParserApiKey::create([
                'provider_id' => $request->get('provider_id'),
                'api_key' => trim($item),
                'status' => $request->has('status') ? $request->get('status') : 0,
            ]);
        }

        return response()->json([
            'status' => 0,
            'message' => 'Ключи добавлены'
        ]);
    }

    /**
     * Обработка и обновление ключа
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(int $id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'key' => ['required'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        ParserApiKey::find($id)->update([
            'provider_id' => $request->get('provider_id'),
            'api_key' => trim($request->get('key')) ?? null,
            'status' => $request->has('status') ? $request->get('status') : 0,
        ]);

        return response()->json([
            'status' => 0,
            'message' => 'Ключ обновлен'
        ]);
    }

    /**
     * Удаление ключа
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id)
    {
        $key = ParserApiKey::find($id);
        $oldItems = $key;
        $key->delete();

        return response()->json([
            'status' => 0,
            'message' => "Ключ {$oldItems->api_key} удален"
        ]);
    }
}
