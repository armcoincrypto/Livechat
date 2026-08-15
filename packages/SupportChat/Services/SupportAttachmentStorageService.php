<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\SupportAttachment;
use App\Models\SupportConversation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class SupportAttachmentStorageService
{
    /** @var array<string, string> mime => safe storage extension */
    private const MIME_TO_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'video/mp4' => 'mp4',
        'application/pdf' => 'pdf',
    ];

    /** Reject even if finfo mis-detects; never allow these patterns as final mime. */
    private const DANGER_MIME_PREFIXES = [
        'text/',
        'image/svg',
        'application/javascript',
        'application/x-javascript',
        'text/javascript',
        'application/xml',
        'text/html',
        'text/xml',
        'application/xhtml',
        'application/x-httpd-php',
        'application/x-php',
        'application/zip',
        'application/x-zip',
        'application/x-msdownload',
        'application/x-sh',
        'application/x-csh',
    ];

    public function storeVisitorUpload(
        SupportConversation $conversation,
        UploadedFile $file,
        ?string $captionRaw,
    ): SupportAttachment {
        if (! $file->isValid()) {
            $this->logReject('invalid_upload', $conversation->id, null, null);
            throw new HttpException(422, 'Invalid file upload.');
        }

        if ($file->getSize() === false || (int) $file->getSize() < 1) {
            $this->logReject('empty_file', $conversation->id, null, null);
            throw new HttpException(422, 'Empty file.');
        }

        $realPath = $file->getRealPath();
        if ($realPath === false || $realPath === '') {
            $this->logReject('missing_temp_path', $conversation->id, null, null);
            throw new HttpException(422, 'Could not read uploaded file.');
        }

        if ($this->containsDangerousTextPrefix($realPath)) {
            $this->logReject('dangerous_content_prefix', $conversation->id, null, null);
            throw new HttpException(422, 'This file type is not allowed.');
        }

        $mime = strtolower(trim((string) $file->getMimeType()));
        if ($mime === '') {
            $this->logReject('empty_mime', $conversation->id, null, null);
            throw new HttpException(422, 'Could not determine file type.');
        }

        if ($this->isDangerousMime($mime)) {
            $this->logReject('dangerous_mime', $conversation->id, $mime, null);
            throw new HttpException(422, 'This file type is not allowed.');
        }

        $allowed = $this->allowedMimesFromConfig();
        if (! in_array($mime, $allowed, true)) {
            $this->logReject('mime_not_allowed', $conversation->id, $mime, null);
            throw new HttpException(422, 'This file type is not allowed.');
        }

        $maxBytes = $this->maxBytesForMime($mime);
        $size = (int) $file->getSize();
        if ($size > $maxBytes) {
            $this->logReject('file_too_large', $conversation->id, $mime, $size);
            throw new HttpException(422, 'File is too large.');
        }

        $ext = self::MIME_TO_EXT[$mime];
        $disk = (string) config('support_chat.attachments.disk', 'support_chat_private');
        $prefix = trim((string) config('support_chat.attachments.path_prefix', 'support-chat/attachments'), '/');
        $random = bin2hex(random_bytes(16));
        $relativePath = $prefix.'/'.$conversation->id.'/'.$random.'.'.$ext;

        $sha256 = hash_file('sha256', $realPath);
        if ($sha256 === false) {
            $sha256 = null;
        }

        $caption = $this->sanitizeCaption($captionRaw);
        $originalName = $this->sanitizeOriginalName($file->getClientOriginalName());

        $stream = fopen($realPath, 'rb');
        if ($stream === false) {
            $this->logReject('read_failed', $conversation->id, $mime, $size);
            throw new HttpException(422, 'Could not read uploaded file.');
        }

        try {
            $stored = Storage::disk($disk)->put($relativePath, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $stored) {
            $this->logReject('storage_write_failed', $conversation->id, $mime, $size);
            throw new HttpException(500, 'Could not store file.');
        }

        try {
            return DB::transaction(function () use (
                $conversation,
                $disk,
                $relativePath,
                $originalName,
                $mime,
                $size,
                $sha256,
                $caption,
            ): SupportAttachment {
                $attachment = new SupportAttachment;
                $attachment->support_conversation_id = (int) $conversation->id;
                $attachment->support_message_id = null;
                $attachment->sender_type = SupportAttachment::SENDER_VISITOR;
                $attachment->disk = $disk;
                $attachment->path = $relativePath;
                $attachment->original_name = $originalName;
                $attachment->mime_type = $mime;
                $attachment->size_bytes = $size;
                $attachment->sha256 = $sha256;
                $attachment->caption = $caption;
                $attachment->save();

                SupportChatDiagnosticsLog::attachmentUploaded([
                    'conversation_id' => $attachment->support_conversation_id,
                    'attachment_id' => $attachment->id,
                    'mime_type' => $attachment->mime_type,
                ]);

                return $attachment;
            });
        } catch (\Throwable $e) {
            Storage::disk($disk)->delete($relativePath);
            throw $e;
        }
    }

    /**
     * Store bytes from Telegram operator inbound (forum topic). Returns null on validation/storage failure (caller logs).
     */
    public function storeOperatorTelegramBinary(
        SupportConversation $conversation,
        string $binary,
        string $mime,
        ?string $originalName,
        ?string $caption,
        int $telegramSourceMessageId,
        string $telegramSourceFileId,
    ): ?SupportAttachment {
        if ($binary === '') {
            $this->logReject('empty_binary', $conversation->id, null, 0);

            return null;
        }

        if ($this->containsDangerousBinaryPrefix($binary)) {
            $this->logReject('dangerous_content_prefix', $conversation->id, null, strlen($binary));

            return null;
        }

        $mime = strtolower(trim($mime));
        if ($mime === '') {
            $this->logReject('empty_mime', $conversation->id, null, null);

            return null;
        }

        if ($this->isDangerousMime($mime)) {
            $this->logReject('dangerous_mime', $conversation->id, $mime, null);

            return null;
        }

        if (! $this->isOperatorInboundMimeAllowed($mime)) {
            $this->logReject('mime_not_allowed', $conversation->id, $mime, null);

            return null;
        }

        $size = strlen($binary);
        $maxBytes = $this->maxBytesForMime($mime);
        if ($maxBytes < 1 || $size > $maxBytes) {
            $this->logReject('file_too_large', $conversation->id, $mime, $size);

            return null;
        }

        if (! isset(self::MIME_TO_EXT[$mime])) {
            $this->logReject('mime_not_mapped', $conversation->id, $mime, null);

            return null;
        }

        $ext = self::MIME_TO_EXT[$mime];
        $disk = (string) config('support_chat.attachments.disk', 'support_chat_private');
        $prefix = trim((string) config('support_chat.attachments.path_prefix', 'support-chat/attachments'), '/');
        $random = bin2hex(random_bytes(16));
        $relativePath = $prefix.'/'.$conversation->id.'/tg-op-'.$random.'.'.$ext;

        $sha256 = hash('sha256', $binary);

        try {
            return DB::transaction(function () use (
                $conversation,
                $disk,
                $relativePath,
                $originalName,
                $mime,
                $size,
                $sha256,
                $caption,
                $telegramSourceMessageId,
                $telegramSourceFileId,
                $binary,
            ): SupportAttachment {
                $stored = Storage::disk($disk)->put($relativePath, $binary);
                if (! $stored) {
                    $this->logReject('storage_write_failed', $conversation->id, $mime, $size);
                    throw new \RuntimeException('storage_write_failed');
                }

                $attachment = new SupportAttachment;
                $attachment->support_conversation_id = (int) $conversation->id;
                $attachment->support_message_id = null;
                $attachment->sender_type = SupportAttachment::SENDER_OPERATOR;
                $attachment->disk = $disk;
                $attachment->path = $relativePath;
                $attachment->original_name = $this->sanitizeOriginalName((string) ($originalName ?? '')) ?? null;
                $attachment->mime_type = $mime;
                $attachment->size_bytes = $size;
                $attachment->sha256 = $sha256;
                $attachment->caption = $this->sanitizeCaption($caption);
                $attachment->telegram_message_id = $telegramSourceMessageId;
                $attachment->telegram_file_id = $telegramSourceFileId;
                $attachment->save();

                return $attachment;
            });
        } catch (\Throwable $e) {
            Storage::disk($disk)->delete($relativePath);

            return null;
        }
    }

    private function isOperatorInboundMimeAllowed(string $mime): bool
    {
        return in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true);
    }

    private function containsDangerousBinaryPrefix(string $binary): bool
    {
        $chunk = substr($binary, 0, 8192);
        if ($chunk === '' || $chunk === false) {
            return false;
        }
        $lower = strtolower($chunk);

        return str_contains($lower, '<?php')
            || str_contains($lower, '<?=')
            || str_contains($lower, '<script');
    }

    public function findForConversation(SupportConversation $conversation, int $attachmentId): ?SupportAttachment
    {
        return SupportAttachment::query()
            ->where('support_conversation_id', (int) $conversation->id)
            ->whereKey($attachmentId)
            ->first();
    }

    /**
     * Remove an operator inbound attachment row + file (used when operator message persistence is skipped).
     */
    public function discardOperatorInboundAttachment(SupportAttachment $attachment): void
    {
        if ($attachment->sender_type !== SupportAttachment::SENDER_OPERATOR) {
            return;
        }
        try {
            Storage::disk($attachment->disk)->delete($attachment->path);
        } catch (\Throwable) {
            // best-effort
        }
        $attachment->forceDelete();
    }

    public function inlineDispositionForMime(string $mime): bool
    {
        return str_starts_with($mime, 'image/') || $mime === 'application/pdf';
    }

    public function safeDownloadFileName(?string $originalName, string $mime): string
    {
        $base = $originalName !== null && $originalName !== '' ? basename($originalName) : 'file';
        $base = str_replace(["\0", '/', '\\'], '', $base);
        if ($base === '') {
            $base = 'file';
        }
        if (mb_strlen($base, 'UTF-8') > 120) {
            $base = mb_substr($base, 0, 117, 'UTF-8').'…';
        }

        $ext = self::MIME_TO_EXT[$mime] ?? null;
        if ($ext !== null && ! preg_match('/\.'.preg_quote($ext, '/').'$/i', $base)) {
            $base .= '.'.$ext;
        }

        return $base;
    }

    private function allowedMimesFromConfig(): array
    {
        $raw = config('support_chat.attachments.allowed_mimes', []);
        if (! is_array($raw)) {
            return array_keys(self::MIME_TO_EXT);
        }

        $out = [];
        foreach ($raw as $m) {
            if (! is_string($m)) {
                continue;
            }
            $m = strtolower(trim($m));
            if ($m !== '' && isset(self::MIME_TO_EXT[$m])) {
                $out[] = $m;
            }
        }

        return $out !== [] ? array_values(array_unique($out)) : array_keys(self::MIME_TO_EXT);
    }

    private function maxBytesForMime(string $mime): int
    {
        $imageMb = max(1, (int) config('support_chat.attachments.max_image_mb', 5));
        $videoMb = max(1, (int) config('support_chat.attachments.max_video_mb', 25));
        $pdfMb = max(1, (int) config('support_chat.attachments.max_pdf_mb', 10));

        if (str_starts_with($mime, 'image/')) {
            return $imageMb * 1024 * 1024;
        }
        if ($mime === 'video/mp4') {
            return $videoMb * 1024 * 1024;
        }
        if ($mime === 'application/pdf') {
            return $pdfMb * 1024 * 1024;
        }

        return 0;
    }

    private function isDangerousMime(string $mime): bool
    {
        foreach (self::DANGER_MIME_PREFIXES as $p) {
            if (str_starts_with($mime, $p)) {
                return true;
            }
        }

        return false;
    }

    private function containsDangerousTextPrefix(string $path): bool
    {
        $h = @fopen($path, 'rb');
        if ($h === false) {
            return true;
        }
        try {
            $chunk = fread($h, 8192);
            if ($chunk === false || $chunk === '') {
                return false;
            }
            $lower = strtolower($chunk);

            return str_contains($lower, '<?php')
                || str_contains($lower, '<?=')
                || str_contains($lower, '<script');
        } finally {
            fclose($h);
        }
    }

    private function sanitizeCaption(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $t = trim(strip_tags($raw));
        if ($t === '') {
            return null;
        }

        return mb_substr($t, 0, 500, 'UTF-8');
    }

    private function sanitizeOriginalName(string $name): ?string
    {
        $base = basename(str_replace("\0", '', $name));
        $base = trim($base);
        if ($base === '' || $base === '.' || $base === '..') {
            return null;
        }

        return mb_substr($base, 0, 255, 'UTF-8');
    }

    private function logReject(string $reason, int $conversationId, ?string $mime, ?int $sizeBytes): void
    {
        SupportChatMetrics::incrementAttachmentUploadFailed();
        SupportChatDiagnosticsLog::attachmentFailed([
            'reason' => $reason,
            'error_code' => $reason,
            'conversation_id' => $conversationId,
            'mime_type' => $mime,
        ]);
    }
}
