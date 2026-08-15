<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Http\Controllers\Public;

use iEXPackages\SupportChat\Contracts\SupportChatServiceInterface;
use iEXPackages\SupportChat\Http\Requests\StoreSupportConversationRequest;
use iEXPackages\SupportChat\Http\Resources\SupportMessageResource;
use Illuminate\Http\JsonResponse;

final class ConversationController
{
    public function __construct(
        private readonly SupportChatServiceInterface $supportChat,
    ) {}

    public function store(StoreSupportConversationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $payload = $this->supportChat->createConversation(
            $validated,
            $request->ip(),
            $request->userAgent(),
        );

        $conversation = $payload['conversation'];
        $accessToken = $payload['access_token'];
        $messages = $payload['messages'];

        return response()->json([
            'conversation_uuid' => $conversation->uuid,
            'public_support_id' => $conversation->public_support_id,
            'status' => $conversation->status,
            'waiting_on' => $conversation->waitingOn(),
            'access_token' => $accessToken,
            'messages' => SupportMessageResource::collection($messages)->resolve(),
            'message_states_enabled' => filter_var(config('support_chat.message_states_enabled', false), FILTER_VALIDATE_BOOLEAN),
        ], 201);
    }
}
