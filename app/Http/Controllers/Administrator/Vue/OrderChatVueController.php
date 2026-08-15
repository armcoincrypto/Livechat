<?php

namespace App\Http\Controllers\Administrator\Vue;

use App\Events\OrderChatSentEvent;
use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskMessage;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @class OrderChatVueController
 * @description Работа с чатом в конкретной заявке
 *
 * @version 2.0
 * @author iEXExchanger
 */
class OrderChatVueController extends Controller
{
    /**
     * Получаем все сообщения
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function listChats(Request $request): JsonResponse
    {
        $order_id = (int)$request->get('order_id');

        if(isset($request->onlyCount) and $request->onlyCount) {
            $messagesAll = TaskMessage::where('id_task', $order_id)->count();

            return response()->json([
                'count' => $messagesAll
            ]);
        }

        $messagesAll = TaskMessage::where('id_task', $order_id)->get();
        $messages = collect($messagesAll)->map(function ($item)
        {
            if ($item->type_user == 0) {
                $user = [
                    'id' => $item->user->id,
                    'name' => $item->user->name,
                    'email' => $item->user->email
                ];
            } else {
                $user = [
                    'id' => $item->manager->id,
                    'name' => $item->manager->name,
                    'email' => $item->manager->email
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
            'count' => count($messagesAll)
        ]);
    }

    /**
     * Добавляем новое сообщение для клиента
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function addMessage(Request $request)
    {
        if (\Str::length($request->message) == 0)
        {
            return response()->json([
                'status' => 1,
                'message' => 'Сообщение не добавлено',
            ]);
        }

        $order_id = $request->get('order_id');
        $orderData = Task::select('id', 'id_user', 'public_id')->where('id', $order_id)->first();

        // Добавление сообщение в историю
        TaskMessage::create([
            'user_id' => $orderData->id_user,
            'id_task' => $order_id,
            'message' => security_xss($request->message),
            'id_manager' => \auth()->id(),
            'type_user' => 1,
            'is_view' => 1,
            'is_read' => 1
        ]);

        try {
            broadcast(new OrderChatSentEvent($orderData->public_id, 'user'))->toOthers();
        }catch (BroadcastException $exception) {

        }


        return response()->json([
            'status' => 0
        ]);
    }
}
