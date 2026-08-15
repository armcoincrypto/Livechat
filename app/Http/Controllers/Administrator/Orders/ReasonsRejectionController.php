<?php

namespace App\Http\Controllers\Administrator\Orders;

use App\Http\Controllers\Controller;
use App\Models\TaskRejectionStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReasonsRejectionController extends Controller
{
    public function index()
    {
        $items = TaskRejectionStatus::all()->map(function ($item) {
           return [
               'id' => $item->id,
               'name' => $item->name,
               'locale_name' => $item->getTranslations('name')
           ];
        });

        return response()->json([
            'data' => $items
        ]);
    }

    /**
     * Обработка и добавление новой причины
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name.'.config('iexexchanger.default_locale') => 'required|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $item = TaskRejectionStatus::create([
            'name' => $request->name
        ]);

        return response()->json([
            'status' => 0,
            'message' => $item->name . ' успешно добавлен'
        ]);
    }

    /**
     * Обновление причины
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update($id, Request $request)
    {
        $item = TaskRejectionStatus::find($id);

        $validator = Validator::make($request->all(), [
            'name.'.config('iexexchanger.default_locale') => 'required|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $item->update([
            'name' => $request->name
        ]);

        return response()->json([
            'status' => 0,
            'message' => $item->name . ' успешно обновлен'
        ]);
    }

    /**
     * Удаление причины
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $item = TaskRejectionStatus::find($id);
        if ($item->is_not_delete == 1)
        {
            return response()->json([
                'status' => 1,
                'message' => 'Нельзя удалять эту причину'
            ]);
        }

        $item->delete();

        return response()->json([
            'status' => 0,
            'message' => 'Выбранная причина успешно удалена'
        ]);
    }
}
