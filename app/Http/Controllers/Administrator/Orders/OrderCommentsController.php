<?php

namespace App\Http\Controllers\Administrator\Orders;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Orders\OrderCommentResources;
use App\Models\TaskComment;
use App\Models\TaskFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class OrderCommentsController extends Controller
{
    /**
     * Получаем список всех комментариев
     *
     * @param int $id
     * @return OrderCommentResources
     */
    public function index(int $id)
    {
        $order = TaskComment::where('id_task', $id)->get();
        return new OrderCommentResources($order ?? []);
    }

    /**
     * Прикрепляем новый файл
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function store(int $id, Request $request)
    {
        $validator = Validator::make($request->all(),  [
            'message' => ['required']
        ]);

        if($validator->fails())
        {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $item = TaskComment::create([
            'id_manager' => $request->user()->id,
            'id_task' => $id,
            'message' => security_xss($request->message)
        ]);


        return response()->json([
            'status' => 0,
            'message' => 'Сообщение успешно добавлено',
            'item' => [
                'id' => $item->id,
                'class_style' => $item->class_style,
                'created_at' => Carbon::parse($item->created_at)->diffForHumans(),
                'message' => $item->message,
                'user' => [
                    'id' => $item->user?->id,
                    'name' => $item->user?->name,
                ]
            ]
        ]);
    }

    /**
     * Удаляем прикрепленный элемент
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id)
    {
        $item = TaskComment::findOrFail($id);
        $item->delete();

        return response()->json([
            'status' => 0,
            'message' => 'Комментарий успешно удален'
        ]);
    }
}
