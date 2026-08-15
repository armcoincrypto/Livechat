<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class XssFilterMiddleware
{
    /**
     * Пути, которые НЕ чистим (dot-notation + wildcard).
     */
    private array $except = [
        'password',
        'password_confirmation',

        // Примеры:
        // 'page_content',        // если там HTML/WYSIWYG
        // 'blocks.*.html',
        // 'blocks.*',            // исключить всю ветку blocks
    ];

    /**
     * Пути, где нужно сохранять переводы строк (textarea).
     */
    private array $preserveNewlines = [
        // 'comment',
        // 'description',
        // 'notes',
        // 'items.*.comment',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $request->query->replace(
            $this->cleanArray($request->query->all(), '')
        );

        $request->request->replace(
            $this->cleanArray($request->request->all(), '')
        );

        if ($request->isJson()) {
            $request->json()->replace(
                $this->cleanArray($request->json()->all(), '')
            );
        }

        return $next($request);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function cleanArray(array $data, string $path): array
    {
        foreach ($data as $key => $value) {
            $currentPath = $path === '' ? (string) $key : $path . '.' . $key;

            if ($this->isExcepted($currentPath)) {
                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->cleanArray($value, $currentPath);
                continue;
            }

            if (is_string($value)) {
                $keepNewlines = $this->shouldPreserveNewlines($currentPath);
                $data[$key] = $this->cleanString($value, $keepNewlines);
                continue;
            }

            // Остальные типы (int/float/bool/null/UploadedFile/объекты) не трогаем
        }

        return $data;
    }

    private function cleanString(string $value, bool $preserveNewlines): string
    {
        // NBSP/тонкие/невидимые пробелы из копипаста
        $value = str_replace(
            ["\u{00A0}", "\u{202F}", "\u{2009}", "\u{FEFF}"],
            ' ',
            $value
        );

        // Нормализуем переводы строк, если нужно сохранить
        if ($preserveNewlines) {
            $value = str_replace(["\r\n", "\r"], "\n", $value);
        }

        // Удаляем управляющие символы (но при preserveNewlines сохраняем \n и \t)
        $value = $this->removeControlCharacters($value, $preserveNewlines);

        // Твой sanitizer (важно: он сейчас strip_tags + схлопывание пробелов)
        // Поэтому для preserveNewlines лучше передать режим через отдельную функцию/параметр,
        // но если пока нет — хотя бы не потеряем \n до вызова.
        $cleaned = security_xss($value);

        if (is_string($cleaned)) {
            $value = $cleaned;
        }

        return $value;
    }

    private function removeControlCharacters(string $value, bool $preserveNewlines): string
    {
        $value = str_replace("\0", '', $value);

        if ($preserveNewlines) {
            // оставляем \n (0x0A) и \t (0x09)
            return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $value) ?? $value;
        }

        // single-line: убираем все control chars
        return preg_replace('/[\x00-\x1F\x7F]+/u', '', $value) ?? $value;
    }

    private function isExcepted(string $path): bool
    {
        foreach ($this->except as $rule) {
            if ($this->matchPath($rule, $path, allowBranch: true)) {
                return true;
            }
        }

        return false;
    }

    private function shouldPreserveNewlines(string $path): bool
    {
        foreach ($this->preserveNewlines as $rule) {
            if ($this->matchPath($rule, $path, allowBranch: false)) {
                return true;
            }
        }

        return false;
    }

    /**
     * matchPath:
     * - rule может быть точным: "a.b.c"
     * - с wildcard сегментом: "items.*.title"
     * - allowBranch=true позволяет "blocks.*" матчить "blocks.0.html" и т.п.
     */
    private function matchPath(string $rule, string $path, bool $allowBranch): bool
    {
        $rule = trim($rule);
        if ($rule === '') {
            return false;
        }

        // Если нужно исключать целую ветку: blocks.* -> blocks.<anything>.<anything>...
        if ($allowBranch && str_ends_with($rule, '.*')) {
            $prefix = substr($rule, 0, -2);
            if ($path === $prefix || str_starts_with($path, $prefix . '.')) {
                return true;
            }
        }

        if ($rule === $path) {
            return true;
        }

        if (str_contains($rule, '*')) {
            $pattern = '#^' . str_replace('\*', '[^.]+', preg_quote($rule, '#')) . '$#u';
            return preg_match($pattern, $path) === 1;
        }

        return false;
    }
}
