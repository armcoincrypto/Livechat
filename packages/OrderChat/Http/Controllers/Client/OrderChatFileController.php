<?php

declare(strict_types=1);

namespace iEXPackages\OrderChat\Http\Controllers\Client;

use App\Models\Task;
use App\Models\TaskMessage;
use iEXPackages\ExchangerClient\Support\AssertOrderVerificationAccess;
use iEXPackages\OrderChat\Support\OrderChatFileStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Possession-gated download for order-chat attachments (private disk).
 */
final class OrderChatFileController
{
    public function __construct(private readonly OrderChatFileStorage $files)
    {
    }

    public function show(Request $request, int $publicId, string $filename): BinaryFileResponse|Response
    {
        $filename = basename($filename);
        if ($filename === '' || !preg_match('/^[\w.\-]+$/', $filename)) {
            return response('Not found', 404);
        }

        $order = Task::wherePublicId($publicId)->first();
        if (!$order || !AssertOrderVerificationAccess::allows($request, $order)) {
            return AssertOrderVerificationAccess::denyResponse();
        }

        $owned = TaskMessage::query()
            ->where('id_task', (int) $order->id)
            ->where('file_path', $filename)
            ->exists();
        if (!$owned) {
            return response('Not found', 404);
        }

        $path = $this->files->absolutePath($filename);
        if ($path === null) {
            return response('Not found', 404);
        }

        $mime = mime_content_type($path) ?: 'application/octet-stream';
        $lower = strtolower($mime);
        if (
            str_contains($lower, 'svg')
            || str_contains($lower, 'html')
            || str_contains($lower, 'javascript')
            || str_contains($lower, 'xml')
        ) {
            return response('Unsupported', 415);
        }

        $safeMime = in_array($lower, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true)
            ? $lower
            : 'application/octet-stream';

        return response()->file($path, [
            'Content-Type' => $safeMime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
