<?php

declare(strict_types=1);

namespace App\Support\Security;

final class InputSanitizer
{
    private const DEFAULT_MAX_LENGTH = 65535;

    public const MODE_SINGLE_LINE = 'single_line';
    public const MODE_MULTI_LINE  = 'multi_line';

    /**
     * Режимы HTML:
     * - HTML_STRIP: вырезаем теги полностью (для обычных input)
     * - HTML_ALLOW_SAFE: сохраняем HTML, но вычищаем опасные конструкции (WYSIWYG)
     * - HTML_RAW: вообще не трогаем HTML (опасно, только если дальше вывод строго экранирован)
     */
    public const HTML_STRIP      = 'strip';
    public const HTML_ALLOW_SAFE = 'allow_safe';
    public const HTML_RAW        = 'raw';

    /**
     * Очистка одиночного значения.
     *
     * Важно:
     * - В HTML режимах НЕ выполняем "схлопывание whitespace", чтобы не ломать разметку.
     * - В TEXT режиме (HTML_STRIP) — убираем теги и нормализуем пробелы как раньше.
     *
     * @param string|int|float|bool|null $value
     * @param int|null $maxLength
     * @param string $mode self::MODE_SINGLE_LINE | self::MODE_MULTI_LINE
     * @param string $htmlMode self::HTML_STRIP | self::HTML_ALLOW_SAFE | self::HTML_RAW
     */
    public static function clean(
        string|int|float|bool|null $value,
        ?int $maxLength = null,
        string $mode = self::MODE_SINGLE_LINE,
        string $htmlMode = self::HTML_STRIP
    ): string {
        if ($value === null) {
            return '';
        }

        $str = is_bool($value) ? ($value ? '1' : '0') : (string) $value;

        // Невидимые пробелы из копипаста
        $str = str_replace(
            ["\u{00A0}", "\u{202F}", "\u{2009}", "\u{FEFF}"],
            ' ',
            $str
        );

        // Нормализуем переводы строк
        $str = str_replace(["\r\n", "\r"], "\n", $str);

        // Удаляем управляющие символы (в multi_line оставляем \n и \t)
        $str = self::removeControlCharacters(
            $str,
            preserveNewlines: ($mode === self::MODE_MULTI_LINE),
            preserveTabs: true
        );

        // HTML обработка
        if ($htmlMode === self::HTML_STRIP) {

            // Нормализация пробелов только для TEXT режима
            $str = ($mode === self::MODE_MULTI_LINE)
                ? self::normalizeMultiLineWhitespace($str)
                : self::normalizeSingleLineWhitespace($str);

            $str = trim($str);
        } elseif ($htmlMode === self::HTML_ALLOW_SAFE) {
            // WYSIWYG: сохраняем HTML, но чистим опасное
            $str = self::sanitizeHtmlAllowlist($str);

            // В HTML режиме не схлопываем пробелы/переносы (чтобы не ломать разметку)
            $str = trim($str);
        } else {
            // HTML_RAW: ничего не делаем (ОСТОРОЖНО: безопасно только при дальнейшей экранизации вывода)
            $str = trim($str);
        }

        // Ограничиваем длину
        $maxLength ??= self::DEFAULT_MAX_LENGTH;

        if ($maxLength > 0 && mb_strlen($str, 'UTF-8') > $maxLength) {
            $str = mb_substr($str, 0, $maxLength, 'UTF-8');
        }

        return $str;
    }

    /**
     * Рекурсивная очистка массива.
     *
     * @param array $data
     * @param int|null $maxLength
     * @param string $mode
     * @param string $htmlMode
     */
    public static function cleanArray(
        array $data,
        ?int $maxLength = null,
        string $mode = self::MODE_SINGLE_LINE,
        string $htmlMode = self::HTML_STRIP
    ): array {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::cleanArray($value, $maxLength, $mode, $htmlMode);
                continue;
            }

            if (is_string($value) || is_int($value) || is_float($value) || is_bool($value) || $value === null) {
                $data[$key] = self::clean($value, $maxLength, $mode, $htmlMode);
                continue;
            }

            // UploadedFile/объекты/ресурсы — не трогаем
        }

        return $data;
    }

    /**
     * HTML sanitizer (минимальный, без внешних пакетов).
     *
     * Цель: сохранить HTML, но удалить наиболее опасные XSS-вектора:
     * - удаляем опасные теги полностью
     * - удаляем on* обработчики событий
     * - чистим href/src/xlink:href/action/formaction с запретом javascript:/data:/vbscript:
     * - удаляем style (чтобы не ловить экзотику в CSS), при желании можно заменить на allowlist
     *
     * NB: Самый надежный путь — HTMLPurifier. Этот метод — практичный минимум.
     */
    private static function sanitizeHtmlAllowlist(string $html): string
    {
        // 0) Удаляем HTML-комментарии (иногда их используют для обходов)
        $html = preg_replace('#<!--.*?-->#su', '', $html) ?? $html;

        // 1) Выкидываем опасные теги целиком (включая содержимое)
        // svg/math часто используются для XSS — лучше убрать целиком, если не нужны.
        $dangerTags = '(script|style|iframe|object|embed|link|meta|base|svg|math)';
        $html = preg_replace('#<\s*' . $dangerTags . '\b[^>]*>.*?<\s*/\s*\1\s*>#isu', '', $html) ?? $html;
        $html = preg_replace('#<\s*' . $dangerTags . '\b[^>]*/?\s*>#isu', '', $html) ?? $html;

        // 2) Убираем on* атрибуты (onclick, onerror, onload...)
        // Покрывает двойные/одинарные кавычки и unquoted значения.
        $html = preg_replace('#\s+on[a-z]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)#isu', '', $html) ?? $html;

        // 3) Убираем style атрибут целиком (безопаснее; если нужен — делай allowlist)
        $html = preg_replace('#\s+style\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)#isu', '', $html) ?? $html;

        // 4) Чистим URL-атрибуты: запрещаем javascript:, vbscript:, data:
        // Разрешаем: http(s), mailto, tel, /, #, относительные пути.
        $html = preg_replace_callback(
            '#\s+(href|src|xlink:href|action|formaction)\s*=\s*(["\'])(.*?)\2#isu',
            static function (array $m): string {
                $attr = strtolower($m[1]);
                $q = $m[2];
                $url = trim($m[3]);

                // Декодируем сущности, чтобы поймать java&#x0A;script:
                $decoded = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');

                // Убираем пробелы/таб/переносы и управляющие между буквами протокола
                $collapsed = preg_replace('/[\x00-\x20]+/u', '', $decoded) ?? $decoded;
                $lower = strtolower($collapsed);

                if (
                    str_starts_with($lower, 'javascript:') ||
                    str_starts_with($lower, 'vbscript:') ||
                    str_starts_with($lower, 'data:')
                ) {
                    // Удаляем атрибут целиком
                    return '';
                }

                return ' ' . $attr . '=' . $q . $url . $q;
            },
            $html
        ) ?? $html;

        // 5) Также чистим unquoted URL значения (редко, но бывает)
        $html = preg_replace_callback(
            '#\s+(href|src|xlink:href|action|formaction)\s*=\s*([^\s>"\']+)#isu',
            static function (array $m): string {
                $attr = strtolower($m[1]);
                $url = trim($m[2]);

                $decoded = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $collapsed = preg_replace('/[\x00-\x20]+/u', '', $decoded) ?? $decoded;
                $lower = strtolower($collapsed);

                if (
                    str_starts_with($lower, 'javascript:') ||
                    str_starts_with($lower, 'vbscript:') ||
                    str_starts_with($lower, 'data:')
                ) {
                    return '';
                }

                return ' ' . $attr . '=' . $url;
            },
            $html
        ) ?? $html;

        return $html;
    }

    private static function normalizeSingleLineWhitespace(string $value): string
    {
        // Схлопываем любые whitespace (включая \n/\t) в один пробел
        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }

    private static function normalizeMultiLineWhitespace(string $value): string
    {
        // Сохраняем \n, но:
        // - внутри строк схлопываем пробелы/табуляции
        // - убираем пробелы по краям строк
        $lines = explode("\n", $value);

        foreach ($lines as &$line) {
            $line = preg_replace('/[ \t]{2,}/u', ' ', $line) ?? $line;
            $line = trim($line);
        }
        unset($line);

        return implode("\n", $lines);
    }

    /**
     * Удаляет управляющие символы и нулевые байты.
     */
    private static function removeControlCharacters(
        string $value,
        bool $preserveNewlines,
        bool $preserveTabs
    ): string {
        $value = str_replace("\0", '', $value);

        if ($preserveNewlines && $preserveTabs) {
            // сохраняем \n (0x0A) и \t (0x09)
            return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $value) ?? $value;
        }

        if ($preserveNewlines) {
            // сохраняем только \n
            return preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]+/u', '', $value) ?? $value;
        }

        if ($preserveTabs) {
            // сохраняем только \t
            return preg_replace('/[\x00-\x08\x0A-\x1F\x7F]+/u', '', $value) ?? $value;
        }

        return preg_replace('/[\x00-\x1F\x7F]+/u', '', $value) ?? $value;
    }
}
