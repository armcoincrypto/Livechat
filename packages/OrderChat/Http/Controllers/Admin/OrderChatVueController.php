<?php

declare(strict_types=1);

namespace iEXPackages\OrderChat\Http\Controllers\Admin;

use iEXPackages\OrderChat\Contracts\OrderChatServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OrderChatVueController
{
    public function __construct(private readonly OrderChatServiceInterface $chat) {}

    public function listChats(Request $request): JsonResponse
    {
        $orderId = (int)$request->get('order_id');

        if ($request->boolean('onlyCount')) {
            $count = $this->chat->getAdminMessages($orderId)->count();
            return response()->json(['count' => $count]);
        }

        $messagesAll = $this->chat->getAdminMessages($orderId);

        // Тут можешь оставить текущий маппинг 1-в-1 как сейчас,
        // либо вынести в Resource/Transformer внутри пакета.
        $messages = collect($messagesAll)->map(function ($item) {
            $user = ((int)$item->type_user === 0)
                ? ['id' => $item->user->id, 'name' => $item->user->name, 'email' => $item->user->email]
                : ['id' => $item->manager?->id, 'name' => $item->manager?->name, 'email' => $item->manager?->email];

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
                    'file' => $item->file_path ? url('images/order_chat/' . $item->file_path) : null,
                ]
            ];
        });

        return response()->json([
            'chats' => $messages,
            'count' => $messagesAll->count(),
        ]);
    }

    public function addMessage(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|integer|min:1',
            'message' => 'required|string|min:1',
        ]);

        $this->chat->adminSendMessage(
            orderId: (int)$request->get('order_id'),
            managerId: (int)auth()->id(),
            message: (string)$request->get('message')
        );

        return response()->json(['status' => 0]);
    }
}
