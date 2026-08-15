<?php

declare(strict_types=1);

namespace iEXPackages\OrderChat\Http\Controllers\Admin;

use iEXPackages\OrderChat\Contracts\OrderChatServiceInterface;
use App\Http\Resources\Admin\TaskMessageResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OrderChatsVueController
{
    public function __construct(private readonly OrderChatServiceInterface $chat) {}

    public function getOrders(Request $request): JsonResponse
    {
        $paginator = $this->chat->getChatsList($request->all(), 15);

        return response()->json([
            'items' => new TaskMessageResponse($paginator),
        ]);
    }

    public function allReadById(int $orderId): void
    {
        $this->chat->markChatRead($orderId);
    }

    public function allReadChats(): void
    {
        $this->chat->markAllChatsRead();
    }

    public function getMessagesByChat(int $id): JsonResponse
    {
        $messagesAll = $this->chat->getAdminMessages($id);

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
            'count' => $messages->count(),
        ]);
    }
}
