<?php

declare(strict_types=1);

namespace iEXPackages\OrderChat\Http\Controllers\Client;

use iEXPackages\OrderChat\Contracts\OrderChatServiceInterface;
use iEXPackages\OrderChat\Http\Resources\OrderChatResources;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OrderChatController
{
    public function __construct(private readonly OrderChatServiceInterface $chat) {}

    public function index(int $public_id, Request $request): OrderChatResources|JsonResponse
    {
        $items = $this->chat->getClientMessages($public_id, (string)$request->ip());

        return new OrderChatResources($items);
    }

    public function store(int $public_id, Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'nullable|string',
            'file' => 'nullable|file|mimetypes:image/png,image/jpeg,image/svg+xml,application/pdf|max:10240',
        ]);

        $this->chat->clientSendMessage(
            publicId: $public_id,
            requestIp: (string)$request->ip(),
            message: $request->input('message'),
            file: $request->file('file')
        );

        return response()->json(['status' => 0, 'message' => 'Message sent']);
    }
}
