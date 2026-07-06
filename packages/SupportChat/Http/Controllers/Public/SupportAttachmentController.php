<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Http\Controllers\Public;

use App\Models\SupportAttachment;
use App\Models\SupportConversation;
use iEXPackages\SupportChat\Http\Requests\StoreSupportAttachmentRequest;
use iEXPackages\SupportChat\Http\Resources\SupportMessageResource;
use iEXPackages\SupportChat\Jobs\SendSupportAttachmentToTelegramJob;
use iEXPackages\SupportChat\Services\SupportAttachmentStorageService;
use iEXPackages\SupportChat\Services\SupportChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SupportAttachmentController
{
    public function __construct(
        private readonly SupportAttachmentStorageService $attachments,
        private readonly SupportChatService $supportChat,
    ) {}

    public function store(StoreSupportAttachmentRequest $request, string $uuid): JsonResponse
    {
        /** @var SupportConversation $conversation */
        $conversation = $request->attributes->get('support_conversation');
        if ($conversation->uuid !== $uuid) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $caption = $request->input('caption');
        if ($caption === null || $caption === '') {
            $caption = $request->input('message');
        }

        $attachment = $this->attachments->storeVisitorUpload(
            $conversation,
            $request->file('file'),
            is_string($caption) ? $caption : null,
        );

        $message = $this->supportChat->persistVisitorAttachmentMessage($conversation, $attachment);

        if ($this->shouldQueueTelegramAttachmentForward($attachment)) {
            SendSupportAttachmentToTelegramJob::dispatch((int) $attachment->id)->afterResponse();
        }

        return response()->json([
            'attachment' => [
                'id' => $attachment->id,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => $attachment->size_bytes,
                'original_name' => $attachment->original_name,
                'caption' => $attachment->caption,
                'created_at' => $attachment->created_at?->toIso8601String(),
            ],
            'message' => (new SupportMessageResource($message))->resolve($request),
        ], 201);
    }

    public function show(Request $request, string $uuid, int $attachment): StreamedResponse|JsonResponse
    {
        /** @var SupportConversation $conversation */
        $conversation = $request->attributes->get('support_conversation');
        if ($conversation->uuid !== $uuid) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $row = $this->attachments->findForConversation($conversation, $attachment);
        if ($row === null || ! in_array($row->sender_type, [SupportAttachment::SENDER_VISITOR, SupportAttachment::SENDER_OPERATOR], true)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if (str_contains($row->path, '..')) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if (! Storage::disk($row->disk)->exists($row->path)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $inline = $this->attachments->inlineDispositionForMime($row->mime_type);
        $disposition = $inline ? 'inline' : 'attachment';
        $name = $this->attachments->safeDownloadFileName($row->original_name, $row->mime_type);

        return Storage::disk($row->disk)->response($row->path, $name, [
            'Content-Type' => $row->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ], $disposition);
    }

    private function shouldQueueTelegramAttachmentForward(SupportAttachment $attachment): bool
    {
        if (! filter_var(config('support_chat.attachments.telegram_enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        if (! filter_var(config('support_chat.telegram.enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $mime = strtolower(trim($attachment->mime_type));

        return in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true);
    }
}
