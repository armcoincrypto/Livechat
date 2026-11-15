<?php

declare(strict_types=1);

namespace iEXPackages\OrderChat\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class OrderChatFileStorage
{
    public const DISK_FOLDER = 'order_chat';

    private const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'pdf'];

    private const ALLOWED_MIME = [
        'image/jpeg',
        'image/png',
        'application/pdf',
    ];

    /**
     * Store chat attachment on the private iexexchanger disk (not webroot).
     * Extension is server-derived from MIME — never trust client original extension.
     */
    public function storeOrderChatFile(UploadedFile $file): string
    {
        $mime = strtolower((string) ($file->getMimeType() ?: ''));
        if ($mime === '' || !in_array($mime, self::ALLOWED_MIME, true)) {
            throw new RuntimeException('Unsupported chat attachment type');
        }

        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf',
            default => '',
        };
        if ($ext === '' || !in_array($ext, self::ALLOWED_EXT, true)) {
            throw new RuntimeException('Unsupported chat attachment type');
        }

        $fileName = now()->format('YmdHis').'_'.Str::uuid()->toString().'.'.$ext;
        $relative = self::DISK_FOLDER.'/'.$fileName;

        $stream = fopen($file->getRealPath(), 'r');
        if ($stream === false) {
            throw new RuntimeException('Unable to read chat attachment');
        }
        try {
            Storage::disk('iexexchanger-disk')->put($relative, $stream);
        } finally {
            fclose($stream);
        }

        return $fileName;
    }

    /**
     * Resolve absolute path for a stored chat file (private disk, then legacy public).
     */
    public function absolutePath(string $fileName): ?string
    {
        $fileName = basename($fileName);
        if ($fileName === '' || $fileName === '.' || $fileName === '..') {
            return null;
        }

        $private = rtrim((string) config('filesystems.disks.iexexchanger-disk.root'), '/').'/'.self::DISK_FOLDER.'/'.$fileName;
        if (is_file($private)) {
            return $private;
        }

        $legacy = public_path('images/order_chat/'.$fileName);
        if (is_file($legacy)) {
            return $legacy;
        }

        return null;
    }
}
