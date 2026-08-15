<?php

namespace App\Http\Controllers\Administrator\Tools;

use App\Http\Controllers\Controller;
use App\Models\SocialAuthSystem;
use Illuminate\Http\Request;

class AuthSystemController extends Controller
{
    public function index()
    {
        $authSystem = SocialAuthSystem::orderBy('sorting')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'attributes' => [
                    'name' => $item->name,
                    'alias' => $item->alias,
                    'client_id' => $item->client_id,
                    'client_secret' => $item->client_secret,
                    'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                    'created_at_human' => $item->created_at->diffForHumans(),
                    'updated_at' => $item->updated_at->translatedFormat('d M Y H:i'),
                    'updated_at_human' => $item->updated_at->diffForHumans(),
                    'status' => (bool)$item->status
                ]
            ];
        });

        return response()->json([
            'data' => $authSystem,
            'total' => count($authSystem)
        ]);
    }

    /**
     * Добавить нового сервиса
     */
    public function create()
    {
        return view('admin.tools.auth-system.create');
    }

    /**
     * Обработка и добавление нового сервиса
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        if($request->has('updateField'))
        {
            if($request->get('updateField') == 'status')
            {
                SocialAuthSystem::find((int)$request->id)->update([
                    'status' => (int)$request->status
                ]);

                return response()->json([
                    'status' => 0
                ]);
            }
        }

        $validator = \Validator::make($request->all(), [
            'name' => ['required'],
            'alias' => ['required'],
        ]);


        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }


        $item = SocialAuthSystem::create([
            'name' => $request->get('name'),
            'alias' => $request->get('alias'),
            'status' => ($request->has('status') ? (int)$request->get('status') : 0),
            'client_id' => $request->has('client_id') ? $request->get('client_id') : null,
            'client_secret' => $request->has('client_secret') ? $request->get('client_secret') : null,
        ]);

        return response()->json([
            'status' => 0,
            'message' => 'Сервис '.$item->name.' успешно добавлен'
        ]);

    }

    /**
     * Форма редактирования сервиса
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(int $id)
    {
        $item = SocialAuthSystem::find($id);

        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'name' => $item->name,
                'alias' => $item->alias,
                'client_id' => $item->client_id,
                'client_secret' => $item->client_secret,
                'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                'created_at_human' => $item->created_at->diffForHumans(),
                'updated_at' => $item->updated_at->translatedFormat('d M Y H:i'),
                'updated_at_human' => $item->updated_at->diffForHumans(),
                'status' => (bool)$item->status
            ]
        ]);
    }

    /**
     * Обработка и обновление сервиса
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(int $id, Request $request)
    {
        $item = SocialAuthSystem::find($id);

        $validator = \Validator::make($request->all(), [
            'name' => ['required'],
            'alias' => ['required'],
        ]);


        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

            $item->update([
                'name' => $request->get('name'),
                'alias' => $request->get('alias'),
                'status' => ($request->has('status') ? (int)$request->get('status') : 0),
                'client_id' => $request->has('client_id') ? $request->get('client_id') : null,
                'client_secret' => $request->has('client_secret') ? $request->get('client_secret') : null,
            ]);

        return response()->json([
            'status' => 0,
            'message' => 'Сервис '.$item->name.' успешно обновлен'
        ]);
    }

    /**
     * Удаление сервиса
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Exception
     */
    public function destroy(int $id)
    {
        $item = SocialAuthSystem::find($id);
        $oldItem = $item;
        $item->delete();

        return response()->json([
            'status' => 0,
            'message' => $oldItem->name . ' успешно удален'
        ]);
    }
}
