<?php
namespace App\Http\Controllers\Administrator\Vue;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\TaskMessageResponse;
use App\Models\TaskMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * @class OrderChatsVueController
 * @description Работа со всеми чатами в заявках
 *
 * @version 1.0
 * @author iEXExchanger
*/
class OrderChatsVueController extends Controller
{
    /**
     * Получаем список чатов с сообщениями
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getOrders(Request $request): JsonResponse
    {
        $orders = TaskMessage::query()->filter($request->all())->withCount('unread');

        if(intval($request->searchValue) > 0) {
            $orders->whereHas('task', function ($q) use($request) {
                $q->whereLike('public_id', (int)$request->searchValue)->orwhereLike('id', (int)$request->searchValue);
            });
        }

        $orders->whereIn('id', function($query) {
            $query->from('tasks_messages')->groupBy('id_task')->selectRaw('MAX(id)');
        })->orderByDesc('unread_count')->orderByDesc('created_at');

        return response()->json([
            'items' => new TaskMessageResponse($orders->paginate(15))
        ]);
    }

    /**
     * Если открываем чат, автоматически все сообщения становятся прочитанными
     *
     * @param int $orderId
     * @return void
    */
    public function allReadById(int $orderId): void
    {
        TaskMessage::where('id_task', $orderId)->update(['is_read' => 1]);
    }

    /**
     * Отмечаем все сообщения как прочитанные
     *
     * @return void
     */
    public function allReadChats(): void {
        \DB::table('tasks_messages')->update(['is_read' => 1]);
    }

    /**
     * Получаем все сообщения по выбранному чату
     *
     * @param int $id
     * @return JsonResponse
     */
    public function getMessagesByChat(int $id): JsonResponse
    {
        $messagesAll = TaskMessage::where('id_task', $id)->get();

        $messages = collect($messagesAll)->map(function ($item) {
            if ($item->type_user == 0) {
                $user = [
                    'id' => $item->user->id,
                    'name' => $item->user->name,
                    'email' => $item->user->email
                ];
            } else {
                $user = [
                    'id' => $item->manager?->id,
                    'name' => $item->manager?->name,
                    'email' => $item->manager?->email
                ];
            }

            return [
                'id' => $item->id,
                'created_at' => $item->created_at,
                'attributes' => [
                    'message' => $item->message,
                    'type_user' => $item->type_user,
                    'userId' => $user['id'],
                    'is_read' => $item->is_read,
                    'created_at' => $item->created_at->format('H:i'),
                    'user' => $user,
                    'file' => $item->file_path
                        ? url('images/order_chat/' . $item->file_path)
                        : null,
                ]
            ];
        });

        return response()->json([
            'chats' => $messages,
            'count' => $messages->count()
        ]);
    }
}
