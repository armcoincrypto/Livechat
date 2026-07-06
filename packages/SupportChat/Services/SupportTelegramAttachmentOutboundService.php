<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\SupportAttachment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Sends visitor attachment files to the support Telegram forum topic (P2.2).
 * Failures are logged only; callers must not depend on success.
 */
final class SupportTelegramAttachmentOutboundService
{
    private const CAPTION_MAX = 900;

    /** @var array<string, string> */
    private const MIME_TO_TELEGRAM_FILENAME = [
        'image/jpeg' => 'photo.jpg',
        'image/png' => 'photo.png',
        'image/webp' => 'photo.webp',
        'application/pdf' => 'document.pdf',
    ];

    public function sendVisitorAttachmentIfApplicable(int $supportAttachmentId): void
    {
        $attachment = SupportAttachment::query()
            ->whereKey($supportAttachmentId)
            ->with('conversation')
            ->first();

        if ($attachment === null) {
            return;
        }

        if ($attachment->telegram_message_id !== null) {
            return;
        }

        if ($attachment->sender_type !== SupportAttachment::SENDER_VISITOR) {
            return;
        }

        if (! filter_var(config('support_chat.attachments.enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        if (! filter_var(config('support_chat.attachments.telegram_enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        if (! filter_var(config('support_chat.telegram.enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        if (! filter_var(config('support_chat.telegram.use_forum_topics', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $conversation = $attachment->conversation;
        if ($conversation === null) {
            return;
        }

        $topicId = $conversation->telegram_forum_topic_id;
        if ($topicId === null || (int) $topicId < 1) {
            $this->failTelegramDelivery($attachment, 'no_forum_topic', 'Missing Telegram forum topic');

            return;
        }

        $mime = strtolower(trim($attachment->mime_type));
        $method = $this->resolveTelegramMethod($mime);
        if ($method === null) {
            return;
        }

        $token = $this->normalizeToken((string) config('support_chat.telegram.bot_token', ''));
        $chatId = $this->normalizeChatId(config('support_chat.telegram.group_id'));
        if ($token === '' || $chatId === '') {
            Log::warning('support-chat telegram: attachment_outbound skipped_misconfigured', [
                'support_attachment_id' => $attachment->id,
                'support_conversation_id' => $attachment->support_conversation_id,
            ]);

            return;
        }

        $disk = $attachment->disk;
        $path = $attachment->path;
        if (str_contains($path, '..') || ! Storage::disk($disk)->exists($path)) {
            $this->failTelegramDelivery($attachment, 'missing_file', 'Stored file missing on disk');

            return;
        }

        $absolute = Storage::disk($disk)->path($path);
        if ($absolute === '' || ! is_readable($absolute)) {
            $this->failTelegramDelivery($attachment, 'unreadable_file', 'Stored file not readable');

            return;
        }

        $caption = $this->buildCaption($attachment, $conversation);
        $multipartName = $method === 'sendPhoto' ? 'photo' : 'document';
        $uploadName = self::MIME_TO_TELEGRAM_FILENAME[$mime] ?? 'file.bin';

        $url = "https://api.telegram.org/bot{$token}/{$method}";

        try {
            $response = Http::timeout(90)
                ->acceptJson()
                ->attach($multipartName, file_get_contents($absolute) ?: '', $uploadName)
                ->post($url, [
                    'chat_id' => $chatId,
                    'message_thread_id' => (int) $topicId,
                    'caption' => $caption,
                ]);
        } catch (Throwable $e) {
            $this->failTelegramDelivery($attachment, 'transport_error', $e->getMessage());

            return;
        }

        if (! $response->successful()) {
            $this->failTelegramDelivery($attachment, 'http_error', 'HTTP '.$response->status());

            return;
        }

        $data = $response->json();
        if (! is_array($data) || empty($data['ok'])) {
            $this->failTelegramDelivery($attachment, 'api_rejected', is_array($data) ? (string) ($data['description'] ?? 'api_rejected') : 'api_rejected');

            return;
        }

        $result = $data['result'] ?? null;
        if (! is_array($result) || ! isset($result['message_id'])) {
            $this->failTelegramDelivery($attachment, 'missing_message_id', 'Telegram response missing message_id');

            return;
        }

        $telegramMessageId = (int) $result['message_id'];
        $fileId = $this->extractTelegramFileId($method, $result);

        SupportAttachment::query()->whereKey($attachment->id)->update([
            'telegram_message_id' => $telegramMessageId,
            'telegram_file_id' => $fileId,
            'telegram_delivery_failed_at' => null,
            'telegram_delivery_error' => null,
        ]);

        SupportChatDiagnosticsLog::attachmentUploaded([
            'conversation_id' => $attachment->support_conversation_id,
            'public_support_id' => $conversation->public_support_id,
            'attachment_id' => $attachment->id,
            'telegram_message_id' => $telegramMessageId,
            'telegram_topic_id' => $topicId,
            'mime_type' => $attachment->mime_type,
            'channel' => 'telegram_outbound',
        ]);
    }

    private function failTelegramDelivery(SupportAttachment $attachment, string $errorCode, ?string $detail): void
    {
        app(SupportChatDeliveryDiagnostics::class)->markAttachmentTelegramFailed(
            (int) $attachment->id,
            $detail,
            $errorCode
        );

        SupportChatDiagnosticsLog::telegramSendFailed([
            'conversation_id' => $attachment->support_conversation_id,
            'attachment_id' => $attachment->id,
            'mime_type' => $attachment->mime_type,
            'error_code' => $errorCode,
            'reason' => SupportChatDiagnosticsLog::sanitizeError($detail),
        ]);
    }

    private function resolveTelegramMethod(string $mime): ?string
    {
        if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            if (! filter_var(config('support_chat.attachments.telegram_images_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
                return null;
            }

            return 'sendPhoto';
        }

        if ($mime === 'application/pdf') {
            if (! filter_var(config('support_chat.attachments.telegram_documents_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
                return null;
            }

            return 'sendDocument';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function extractTelegramFileId(string $method, array $result): ?string
    {
        if ($method === 'sendPhoto') {
            $photo = $result['photo'] ?? null;
            if (! is_array($photo) || $photo === []) {
                return null;
            }
            $last = end($photo);
            if (! is_array($last) || ! isset($last['file_id'])) {
                return null;
            }

            return (string) $last['file_id'];
        }

        if ($method === 'sendDocument') {
            $doc = $result['document'] ?? null;
            if (! is_array($doc) || ! isset($doc['file_id'])) {
                return null;
            }

            return (string) $doc['file_id'];
        }

        return null;
    }

    private function buildCaption(SupportAttachment $attachment, \App\Models\SupportConversation $conversation): string
    {
        $lines = [
            '📎 Attachment from visitor',
            '#'.$this->sanitizeCaptionLine((string) ($conversation->public_support_id ?? '—')),
            $this->sanitizeCaptionLine($this->safeOriginalFilenameForCaption($attachment->original_name)),
        ];

        $userCap = $attachment->caption;
        if ($userCap !== null && trim($userCap) !== '') {
            $lines[] = $this->sanitizeCaptionLine($userCap);
        }

        $text = implode("\n", array_filter($lines, static fn (string $l): bool => $l !== ''));
        if (mb_strlen($text, 'UTF-8') > self::CAPTION_MAX) {
            return mb_substr($text, 0, self::CAPTION_MAX - 1, 'UTF-8').'…';
        }

        return $text;
    }

    private function safeOriginalFilenameForCaption(?string $original): string
    {
        if ($original === null || $original === '') {
            return '';
        }
        $base = basename(str_replace("\0", '', $original));
        $base = preg_replace('/[^\p{L}\p{N}._\- ]/u', '_', $base) ?? '';
        $base = trim($base);
        if ($base === '' || $base === '.' || $base === '..') {
            return '';
        }

        return mb_substr($base, 0, 80, 'UTF-8');
    }

    private function sanitizeCaptionLine(string $line): string
    {
        $line = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $line) ?? '';
        $line = trim($line);

        return $line;
    }

    private function normalizeToken(string $token): string
    {
        return trim($token);
    }

    private function normalizeChatId(mixed $groupId): string
    {
        if ($groupId === null) {
            return '';
        }
        if (is_int($groupId) || is_float($groupId)) {
            return (string) (int) $groupId;
        }

        return trim((string) $groupId);
    }
}
