<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\SupportAttachment;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Downloads operator-sent Telegram photos/documents (forum topics) and stores them as support_attachments.
 */
final class SupportTelegramInboundMediaService
{
    public function __construct(
        private readonly SupportAttachmentStorageService $attachments,
        private readonly SupportTelegramOperatorCommandService $operatorCommands,
    ) {}

    /**
     * @return null|array{0: SupportAttachment, 1: string}|array{0: '__skip__', 1: string}
     *         null = not a media message for this handler (plain text / unsupported shape)
     *         tuple with SupportAttachment = success
     *         tuple '__skip__' + reason = media branch gave up; caller may still persist caption/text unless reason is not_admin
     */
    public function tryIngestForumTopicOperatorMedia(
        SupportConversation $conversation,
        SupportMessage $visitorAnchor,
        array $message,
        int $telegramMessageId,
        array $from,
    ): ?array {
        if (! filter_var(config('support_chat.telegram.inbound_attachments_enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $spec = $this->extractInboundMediaSpec($message);
        if ($spec === null) {
            return null;
        }

        if (! empty($spec['skip'])) {
            return ['__skip__', 'video'];
        }

        $operatorUserId = isset($from['id']) ? (int) $from['id'] : 0;
        if (! $this->operatorCommands->isConfiguredSupportGroupAdminOrCreator($operatorUserId)) {
            Log::warning('support-chat telegram: inbound_media_rejected_not_admin', [
                'support_conversation_id' => $conversation->id,
                'telegram_message_id' => $telegramMessageId,
            ]);

            return ['__skip__', 'not_admin'];
        }

        $token = $this->normalizeToken((string) config('support_chat.telegram.bot_token', ''));
        if ($token === '') {
            Log::warning('support-chat telegram: inbound_media_rejected_no_token', [
                'support_conversation_id' => $conversation->id,
            ]);

            return ['__skip__', 'no_token'];
        }

        $binary = $this->downloadTelegramFile($token, $spec['file_id']);
        if ($binary === null) {
            return ['__skip__', 'download_failed'];
        }

        $mime = $this->resolveMime($binary, $spec['declared_mime'] ?? null);
        $caption = isset($message['caption']) && is_string($message['caption']) ? $message['caption'] : null;

        $attachment = $this->attachments->storeOperatorTelegramBinary(
            $conversation,
            $binary,
            $mime,
            $spec['file_name'] ?? null,
            $caption,
            $telegramMessageId,
            $spec['file_id'],
        );

        if ($attachment === null) {
            Log::warning('support-chat telegram: inbound_media_store_rejected', [
                'support_conversation_id' => $conversation->id,
                'telegram_message_id' => $telegramMessageId,
                'declared_mime' => $spec['declared_mime'] ?? null,
                'resolved_mime' => $mime,
            ]);

            return ['__skip__', 'store_failed'];
        }

        $maxLength = (int) config('support_chat.message_max_length', 8000);
        $body = '📎 Attachment';
        if ($caption !== null && trim($caption) !== '') {
            $body = trim(strip_tags($caption));
            if (mb_strlen($body, 'UTF-8') > $maxLength) {
                $body = mb_substr($body, 0, $maxLength, 'UTF-8');
            }
            if ($body === '') {
                $body = '📎 Attachment';
            }
        }

        return [$attachment, $body];
    }

    /**
     * @return array{file_id: string, declared_mime: ?string, file_name: ?string, __skip__?: bool}|null
     */
    private function extractInboundMediaSpec(array $message): ?array
    {
        // Plain operator text uses `text`. Media uses `caption`, not `text`. If `text` is present, never
        // treat the update as inbound media (avoids forum replies being consumed by the media branch).
        if (isset($message['text']) && is_string($message['text']) && trim($message['text']) !== '') {
            return null;
        }

        if (isset($message['photo']) && is_array($message['photo']) && $message['photo'] !== []) {
            $best = null;
            $bestSize = -1;
            foreach ($message['photo'] as $p) {
                if (! is_array($p) || ! isset($p['file_id']) || ! is_string($p['file_id'])) {
                    continue;
                }
                $sz = isset($p['file_size']) ? (int) $p['file_size'] : 0;
                if ($sz >= $bestSize) {
                    $bestSize = $sz;
                    $best = $p['file_id'];
                }
            }
            if ($best === null || $best === '') {
                return null;
            }

            return [
                'file_id' => $best,
                'declared_mime' => 'image/jpeg',
                'file_name' => null,
            ];
        }

        if (isset($message['document']) && is_array($message['document'])) {
            $doc = $message['document'];
            $fid = isset($doc['file_id']) && is_string($doc['file_id']) ? $doc['file_id'] : null;
            if ($fid === null || $fid === '') {
                return null;
            }
            $declared = isset($doc['mime_type']) && is_string($doc['mime_type']) ? strtolower(trim($doc['mime_type'])) : null;
            if ($declared !== null && str_starts_with($declared, 'video/')) {
                Log::warning('support-chat telegram: inbound_media_skipped_video', []);

                return ['skip' => true, 'file_id' => '', 'declared_mime' => null, 'file_name' => null];
            }

            $fname = isset($doc['file_name']) && is_string($doc['file_name']) ? $doc['file_name'] : null;

            return [
                'file_id' => $fid,
                'declared_mime' => $declared,
                'file_name' => $fname,
            ];
        }

        return null;
    }

    private function normalizeToken(string $token): string
    {
        return trim($token);
    }

    private function downloadTelegramFile(string $token, string $fileId): ?string
    {
        try {
            $gr = Http::timeout(25)
                ->acceptJson()
                ->get('https://api.telegram.org/bot'.$token.'/getFile', [
                    'file_id' => $fileId,
                ]);
        } catch (Throwable $e) {
            Log::warning('support-chat telegram: inbound_media_getfile_transport', [
                'exception' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $gr->successful()) {
            Log::warning('support-chat telegram: inbound_media_getfile_http', [
                'status' => $gr->status(),
            ]);

            return null;
        }

        $gj = $gr->json();
        if (! is_array($gj) || empty($gj['ok']) || ! isset($gj['result']['file_path']) || ! is_string($gj['result']['file_path'])) {
            Log::warning('support-chat telegram: inbound_media_getfile_bad_response', []);

            return null;
        }

        $filePath = $gj['result']['file_path'];
        if ($filePath === '' || str_contains($filePath, '..')) {
            return null;
        }

        $url = 'https://api.telegram.org/file/bot'.$token.'/'.$filePath;

        try {
            $bin = Http::timeout(120)->get($url);
        } catch (Throwable $e) {
            Log::warning('support-chat telegram: inbound_media_download_transport', [
                'exception' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $bin->successful()) {
            Log::warning('support-chat telegram: inbound_media_download_http', [
                'status' => $bin->status(),
            ]);

            return null;
        }

        $body = $bin->body();

        return $body !== '' ? $body : null;
    }

    private function resolveMime(string $binary, ?string $declared): string
    {
        $detected = '';
        if (function_exists('finfo_open')) {
            $f = finfo_open(FILEINFO_MIME_TYPE);
            if ($f !== false) {
                $m = finfo_buffer($f, $binary, FILEINFO_MIME_TYPE);
                finfo_close($f);
                if (is_string($m)) {
                    $detected = strtolower(trim($m));
                }
            }
        }

        if ($detected !== '' && $this->isMimeCompatible($declared, $detected)) {
            return $detected;
        }

        if ($declared !== null && $declared !== '') {
            return strtolower(trim($declared));
        }

        return $detected;
    }

    private function isMimeCompatible(?string $declared, string $detected): bool
    {
        if ($declared === null || $declared === '') {
            return true;
        }
        $d = strtolower(trim($declared));
        if ($d === $detected) {
            return true;
        }
        if (str_starts_with($d, 'image/') && str_starts_with($detected, 'image/')) {
            return true;
        }

        return $d === 'application/pdf' && $detected === 'application/pdf';
    }
}
