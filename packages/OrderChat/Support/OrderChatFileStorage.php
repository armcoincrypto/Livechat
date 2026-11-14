<?php

declare(strict_types=1);

namespace iEXPackages\OrderChat\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

final class OrderChatFileStorage
{
    private const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'pdf'];

    private const ALLOWED_MIME = [
        'image/jpeg',
        'image/png',
        'application/pdf',
    ];

    /**
     * Store chat attachment under public/images/order_chat.
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

        $directory = public_path('images/order_chat');

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $fileName = now()->format('YmdHis').'_'.Str::uuid()->toString().'.'.$ext;
        $file->move($directory, $fileName);

        return $fileName;
    }
}
