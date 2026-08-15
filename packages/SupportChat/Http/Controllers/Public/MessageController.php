<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Http\Controllers\Public;

use App\Models\SupportConversation;
use iEXPackages\SupportChat\Contracts\SupportChatServiceInterface;
use iEXPackages\SupportChat\Http\Requests\GetSupportMessagesRequest;
use iEXPackages\SupportChat\Http\Requests\StoreSupportMessageRequest;
use iEXPackages\SupportChat\Http\Resources\SupportMessageResource;
use Illuminate\Http\JsonResponse;

final class MessageController
{
    public function __construct(
        private readonly SupportChatServiceInterface $supportChat,
    ) {}

    public function store(StoreSupportMessageRequest $request, string $uuid): JsonResponse
    {
        /** @var SupportConversation $conversation */
        $conversation = $request->attributes->get('support_conversation');

        $body = (string) $request->validated('message');
        $message = $this->supportChat->addVisitorMessage($conversation, $body);
        $message->load(['attachments']);
        $conversation->refresh();

        return response()->json([
            'message' => (new SupportMessageResource($message))->resolve(),
            'public_support_id' => $conversation->public_support_id,
            'status' => $conversation->status,
            'waiting_on' => $conversation->waitingOn(),
            'message_states_enabled' => filter_var(config('support_chat.message_states_enabled', false), FILTER_VALIDATE_BOOLEAN),
        ], 201);
    }

    public function index(GetSupportMessagesRequest $request, string $uuid): JsonResponse
    {
        /** @var SupportConversation $conversation */
        $conversation = $request->attributes->get('support_conversation');

        $afterId = (int) $request->validated('after_id');
        $limit = (int) $request->validated('limit');

        $result = $this->supportChat->getMessagesSince($conversation, $afterId, $limit);

        return response()->json([
            'conversation_uuid' => $conversation->uuid,
            'public_support_id' => $conversation->public_support_id,
            'status' => $conversation->status,
            'waiting_on' => $conversation->waitingOn(),
            'messages' => SupportMessageResource::collection($result['messages'])->resolve(),
            'has_more' => $result['has_more'],
            'message_states_enabled' => filter_var(config('support_chat.message_states_enabled', false), FILTER_VALIDATE_BOOLEAN),
        ]);
    }
}
