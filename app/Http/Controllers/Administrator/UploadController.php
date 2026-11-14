<?php

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;

/**
 * Authenticated admin media upload.
 *
 * Hardening (20261115):
 * - Route MUST sit behind admin.auth (see admin-frontend.php).
 * - No SVG/ZIP/HTML/scriptable types — public webroot XSS/RCE probes.
 * - Server MIME via UploadedFile::getMimeType() / finfo only (never client-declared).
 * - UUID filenames only; still under public/storage/editor for CMS compatibility
 *   but limited to raster images + PDF.
 */
class UploadController extends Controller
{
    private const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    private const ALLOWED_MIME = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
    ];

    private const BLOCKED_EXT = [
        'php', 'phtml', 'phar', 'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'zsh',
        'exe', 'dll', 'bat', 'cmd', 'com', 'msi', 'jar', 'js', 'mjs', 'cjs',
        'htaccess', 'ini', 'env', 'config', 'sql', 'ps1', 'vbs', 'vb', 'apk',
        'svg', 'svgz', 'html', 'htm', 'xhtml', 'xml', 'zip', 'gz', 'tgz', 'rar',
        '7z', 'txt', 'csv', 'aspx', 'jsp',
    ];

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => [
                'required',
                File::types(self::ALLOWED_EXT)
                    ->max(51200), // 50MB
            ],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('file');
        $size = (int) $file->getSize();
        $originalName = (string) $file->getClientOriginalName();
        $lowerName = strtolower($originalName);

        foreach (self::BLOCKED_EXT as $bad) {
            if (str_contains($lowerName, '.'.$bad.'.') || str_ends_with($lowerName, '.'.$bad)) {
                return $this->rejectType();
            }
        }

        $ext = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension()));
        $ext = $ext !== '' ? preg_replace('/[^a-z0-9]+/i', '', $ext) : '';
        if ($ext === '' || !in_array($ext, self::ALLOWED_EXT, true)) {
            return $this->rejectType();
        }
        if (in_array($ext, self::BLOCKED_EXT, true)) {
            return $this->rejectType();
        }

        // Prefer server-detected MIME only.
        $mime = strtolower((string) ($file->getMimeType() ?: ''));
        if ($mime === '' || !in_array($mime, self::ALLOWED_MIME, true)) {
            return response()->json([
                'status' => 1,
                'message' => 'Mime type is not allowed.',
            ], 422);
        }

        // Extension/MIME pairing (stop PDF/JPEG polyglot confusion).
        if (in_array($ext, ['jpg', 'jpeg'], true) && $mime !== 'image/jpeg') {
            return $this->rejectType();
        }
        if ($ext === 'png' && $mime !== 'image/png') {
            return $this->rejectType();
        }
        if ($ext === 'webp' && $mime !== 'image/webp') {
            return $this->rejectType();
        }
        if ($ext === 'pdf' && $mime !== 'application/pdf') {
            return $this->rejectType();
        }

        $subDir = 'storage/editor/'.date('Y/m');
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

        $filename = Str::uuid()->toString().'.'.$ext;
        $file->move($dir, $filename);

        $url = asset($subDir.'/'.$filename);

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

    private function rejectType(): JsonResponse
    {
        return response()->json([
            'status' => 1,
            'message' => 'File type is not allowed.',
        ], 422);
    }
}
