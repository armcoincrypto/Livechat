<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Http\Resources;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SupportMessage
 */
final class SupportMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'sender_type' => $this->sender_type,
            'sender_label' => $this->resolveSenderLabel(),
            'body' => $this->body,
            'created_at' => $this->created_at?->toIso8601String(),
        ];

        $conversation = $request->attributes->get('support_conversation');
        if ($conversation instanceof SupportConversation
            && $this->relationLoaded('attachments')
            && $this->attachments->isNotEmpty()) {
            $uuid = $conversation->uuid;
            $data['attachments'] = $this->attachments->map(function ($a) use ($uuid): array {
                return [
                    'id' => $a->id,
                    'mime_type' => $a->mime_type,
                    'size_bytes' => $a->size_bytes,
                    'original_name' => $a->original_name,
                    'download_url' => '/client-api/v1/support/conversations/'.rawurlencode((string) $uuid).'/attachments/'.$a->id,
                ];
            })->values()->all();
        }

        if (filter_var(config('support_chat.message_states_enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            $data['seen_by_visitor_at'] = $this->seen_by_visitor_at?->toIso8601String();
        }

        return $data;
    }

    private function resolveSenderLabel(): string
    {
        return match ($this->sender_type) {
            SupportMessage::SENDER_OPERATOR => 'Support',
            SupportMessage::SENDER_SYSTEM => 'Support',
            default => 'You',
        };
    }
}
