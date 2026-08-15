<?php

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;

class UploadController extends Controller
{
    /**
     * Публичная загрузка файла напрямую в /public/storage/editor/YYYY/MM
     * Без Storage facade и без БД. Возвращает прямой URL через asset().
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => [
                'required',
                File::default()
                    ->max(51200) // 50MB
                    ->extensions(['jpg','jpeg','png','webp','gif','svg','pdf','txt','csv','zip']),
            ],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('file');

        // ВАЖНО: size читаем ДО перемещения
        $size = (int) $file->getSize();

        $originalName = (string) $file->getClientOriginalName();
        $lowerName = strtolower($originalName);

        // ---------------------------------------------------------------------
        // 1) Extension allow/deny
        // ---------------------------------------------------------------------
        $ext = strtolower((string) $file->getClientOriginalExtension());

        $allowedExt = ['jpg','jpeg','png','webp','gif','svg','pdf','txt','csv','zip'];

        $blockedExt = [
            'php','phtml','phar','cgi','pl','py','rb','sh','bash','zsh',
            'exe','dll','bat','cmd','com','msi','jar','js','mjs','cjs',
            'htaccess','ini','env','config','sql','ps1','vbs','vb','apk',
        ];

        // Защита от двойных расширений: something.php.jpg
        foreach ($blockedExt as $bad) {
            if (str_contains($lowerName, '.'.$bad.'.') || str_ends_with($lowerName, '.'.$bad)) {
                return response()->json([
                    'status' => 1,
                    'message' => 'File type is not allowed.',
                ], 422);
            }
        }

        if ($ext !== '' && in_array($ext, $blockedExt, true)) {
            return response()->json([
                'status' => 1,
                'message' => 'File type is not allowed.',
            ], 422);
        }

        if ($ext === '' || !in_array($ext, $allowedExt, true)) {
            return response()->json([
                'status' => 1,
                'message' => 'File type is not allowed.',
            ], 422);
        }

        // ---------------------------------------------------------------------
        // 2) MIME allowlist
        // ---------------------------------------------------------------------
        $mime = (string) ($file->getClientMimeType() ?? '');

        $allowedMime = [
            // images
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
            'image/svg+xml',

            // docs
            'application/pdf',
            'text/plain',
            'text/csv',

            // archives
            'application/zip',
            'application/x-zip-compressed',
            'application/octet-stream', // zip иногда приходит так
        ];

        if ($mime === '' || !in_array($mime, $allowedMime, true)) {
            return response()->json([
                'status' => 1,
                'message' => 'Mime type is not allowed.',
            ], 422);
        }

        // ---------------------------------------------------------------------
        // 3) Store directly to public/storage/editor/YYYY/MM (auto mkdir)
        // ---------------------------------------------------------------------
        $subDir = 'storage/editor/' . date('Y/m'); // внутри public/
        $dir = public_path($subDir);

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        if (!is_dir($dir) || !is_writable($dir)) {
            return response()->json([
                'status' => 1,
                'message' => 'Upload directory is not writable.',
            ], 500);
        }

        $safeExt = $ext !== '' ? $ext : strtolower((string) $file->guessExtension());
        $safeExt = $safeExt ? preg_replace('/[^a-z0-9]+/i', '', $safeExt) : '';

        $filename = Str::uuid()->toString() . ($safeExt ? '.'.$safeExt : '');

        // Перемещаем файл напрямую в public
        $file->move($dir, $filename);

        // Прямой публичный URL
        $url = asset($subDir . '/' . $filename);

        return response()->json([
            'status' => 0,
            'data' => [
                'url' => $url,
                'original_name' => $originalName,
                'mime' => $mime,
                'size' => $size,
            ],
        ]);
    }
}
