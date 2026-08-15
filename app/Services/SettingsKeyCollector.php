<?php

declare(strict_types=1);

namespace App\Services;

use iEXPackages\DynamicConfig\DynamicConfigModel;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

final class SettingsKeyCollector
{
    /**
     * @return array{
     *   keys: string[],
     *   sources: array<string, array{json: string[], php: string[]}>
     * }
     */
    public function collect(bool $withSources = false): array
    {
        $json = $this->collectFromJson();
        $php  = $this->collectFromAppSettingsPhpPropertyFields();
        $dyn  = $this->collectFromAppSettingsDynamicModels();
        $used = $this->collectFromIexSettingCalls();

        $allKeys = array_values(array_unique(array_merge(
            $json['keys'],
            $php['keys'],
            $dyn['keys'],
            $used['keys'],
        )));
        sort($allKeys);

        if (!$withSources) {
            return ['keys' => $allKeys, 'sources' => []];
        }

        // key => {json:[], php:[]}
        $sources = [];

        foreach ($json['sources'] as $key => $files) {
            $sources[$key]['json'] = $this->uniqueSorted($files);
            $sources[$key]['php']  = $sources[$key]['php'] ?? [];
        }

        foreach ([$php['sources'], $dyn['sources'], $used['sources']] as $phpSources) {
            foreach ($phpSources as $key => $files) {
                $sources[$key]['php']  = $this->uniqueMerge($sources[$key]['php'] ?? [], $files);
                $sources[$key]['json'] = $sources[$key]['json'] ?? [];
            }
        }

        foreach ($sources as $key => $src) {
            $sources[$key]['json'] = $this->uniqueSorted($src['json'] ?? []);
            $sources[$key]['php']  = $this->uniqueSorted($src['php'] ?? []);
        }

        ksort($sources);

        return [
            'keys' => $allKeys,
            'sources' => $sources,
        ];
    }

    /**
     * Collects keys used in code via iEXSetting(...) calls.
     * Scans only app/ and packages/.
     *
     * Supports:
     *  - iEXSetting('key', ...)
     *  - iEXSetting([ 'k1', "k2" ], ...)
     *
     * IMPORTANT: collects only string literals (no variables/expressions).
     *
     * @return array{keys: string[], sources: array<string, string[]>}
     */
    private function collectFromIexSettingCalls(): array
    {
        $roots = [base_path('app'), base_path('packages')];

        $paths = [];
        foreach ($roots as $root) {
            if (is_dir($root)) {
                $paths[] = $root;
            }
        }

        if ($paths === []) {
            return ['keys' => [], 'sources' => []];
        }

        $finder = (new Finder())
            ->files()
            ->in($paths)
            ->name('*.php');

        $keys = [];
        $sources = [];

        foreach ($finder as $file) {
            $fullPath = $file->getRealPath() ?: $file->getPathname();
            $content  = File::get($fullPath);
            $relPath  = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $fullPath);

            $found = $this->extractIexSettingKeysFromContent($content);
            if ($found === []) {
                continue;
            }

            foreach ($found as $k) {
                $keys[] = $k;
                $sources[$k][] = $relPath;
            }
        }

        $keys = array_values(array_unique($keys));
        sort($keys);

        foreach ($sources as $k => $list) {
            $sources[$k] = $this->uniqueSorted($list);
        }

        return ['keys' => $keys, 'sources' => $sources];
    }

    /**
     * Извлекает строковые ключи из:
     *  - iEXSetting('key', ...)
     *  - iEXSetting([ 'k1', "k2" ], ...)
     *
     * @return string[]
     */
    private function extractIexSettingKeysFromContent(string $content): array
    {
        $out = [];
        $len = strlen($content);
        $i = 0;

        while ($i < $len) {
            $pos = strpos($content, 'iEXSetting', $i);
            if ($pos === false) {
                break;
            }

            $i = $pos + 9; // strlen('iEXSetting')

            // skip spaces
            while ($i < $len && ctype_space($content[$i])) {
                $i++;
            }

            if ($i >= $len || $content[$i] !== '(') {
                continue;
            }

            $i++; // after '('

            while ($i < $len && ctype_space($content[$i])) {
                $i++;
            }

            if ($i >= $len) {
                break;
            }

            $ch = $content[$i];

            // Case 1: first arg is string
            if ($ch === "'" || $ch === '"') {
                [$str, $next] = $this->readQuotedString($content, $i);
                if ($str !== null) {
                    $key = trim($str);
                    if ($key !== '') {
                        $out[] = $key;
                    }
                }
                $i = $next;
                continue;
            }

            // Case 2: first arg is array [...]
            if ($ch === '[') {
                [$inside, $next] = $this->readBalancedSquareBrackets($content, $i);
                if ($inside !== null) {
                    foreach ($this->extractAllStringLiterals($inside) as $k) {
                        $k = trim($k);
                        if ($k !== '') {
                            $out[] = $k;
                        }
                    }
                }
                $i = $next;
                continue;
            }

            // otherwise skip this call
            $i++;
        }

        return array_values(array_unique($out));
    }

    /**
     * @return string[]
     */
    private function extractAllStringLiterals(string $chunk): array
    {
        $out = [];
        $len = strlen($chunk);
        $i = 0;

        while ($i < $len) {
            $ch = $chunk[$i];

            if ($ch === "'" || $ch === '"') {
                [$str, $next] = $this->readQuotedString($chunk, $i);
                if ($str !== null) {
                    $out[] = $str;
                }
                $i = $next;
                continue;
            }

            $i++;
        }

        return $out;
    }

    /**
     * Reads a quoted string starting at $pos (which must be ' or ").
     *
     * @return array{0: string|null, 1: int} [string, nextPosAfterString]
     */
    private function readQuotedString(string $s, int $pos): array
    {
        $len = strlen($s);
        $q = $s[$pos];
        $i = $pos + 1;

        $buf = '';

        while ($i < $len) {
            $ch = $s[$i];

            if ($ch === '\\') {
                // escape next char
                if ($i + 1 < $len) {
                    $buf .= $s[$i + 1];
                    $i += 2;
                    continue;
                }
                $i++;
                continue;
            }

            if ($ch === $q) {
                return [$buf, $i + 1];
            }

            $buf .= $ch;
            $i++;
        }

        return [null, $len];
    }

    /**
     * Reads balanced square brackets starting at '['.
     *
     * @return array{0: string|null, 1: int} [inside, nextPosAfterClosingBracket]
     */
    private function readBalancedSquareBrackets(string $s, int $openPos): array
    {
        $len = strlen($s);
        $depth = 0;

        for ($i = $openPos; $i < $len; $i++) {
            $ch = $s[$i];

            // skip strings inside array to avoid counting brackets there
            if ($ch === "'" || $ch === '"') {
                [, $next] = $this->readQuotedString($s, $i);
                $i = $next - 1;
                continue;
            }

            if ($ch === '[') {
                $depth++;
                continue;
            }

            if ($ch === ']') {
                $depth--;
                if ($depth === 0) {
                    $inside = substr($s, $openPos + 1, $i - ($openPos + 1));
                    return [$inside, $i + 1];
                }
            }
        }

        return [null, $len];
    }

    /**
     * @return array{keys: string[], sources: array<string, string[]>}
     */
    private function collectFromJson(): array
    {
        $dir = storage_path('app/iexexchanger/settings');

        if (!is_dir($dir)) {
            return ['keys' => [], 'sources' => []];
        }

        $finder = (new Finder())
            ->files()
            ->in($dir)
            ->name('*.json');

        $keys = [];
        $sources = [];

        foreach ($finder as $file) {
            $path = $file->getRealPath() ?: $file->getPathname();

            $raw = File::get($path);
            $data = json_decode($raw, true);

            if (!is_array($data)) {
                continue;
            }

            if (isset($data['locales']) && is_array($data['locales'])) {
                foreach ($data['locales'] as $k) {
                    if (!is_string($k) || trim($k) === '') {
                        continue;
                    }
                    $k = trim($k);
                    $keys[] = $k;
                    $sources[$k][] = $path;
                }
            }

            if (isset($data['default']) && is_array($data['default'])) {
                foreach (array_keys($data['default']) as $k) {
                    if (!is_string($k) || trim($k) === '') {
                        continue;
                    }
                    $k = trim($k);
                    $keys[] = $k;
                    $sources[$k][] = $path;
                }
            }
        }

        $keys = array_values(array_unique($keys));
        sort($keys);

        foreach ($sources as $k => $list) {
            $sources[$k] = $this->uniqueSorted($list);
        }

        return ['keys' => $keys, 'sources' => $sources];
    }

    /**
     * Собирает ключи из App\Settings\... по protected $fields = [ ... ];
     *
     * @return array{keys: string[], sources: array<string, string[]>}
     */
    private function collectFromAppSettingsPhpPropertyFields(): array
    {
        $dir = app_path('Settings');

        if (!is_dir($dir)) {
            return ['keys' => [], 'sources' => []];
        }

        $finder = (new Finder())
            ->files()
            ->in($dir)
            ->name('*.php');

        $keys = [];
        $sources = [];

        foreach ($finder as $file) {
            $path = $file->getRealPath() ?: $file->getPathname();
            $content = File::get($path);

            $blocks = $this->extractProtectedArrayBlocks($content, 'fields');

            foreach ($blocks as $block) {
                foreach ($this->extractAssocArrayKeys($block) as $k) {
                    $keys[] = $k;
                    $sources[$k][] = $path;
                }
            }
        }

        $keys = array_values(array_unique($keys));
        sort($keys);

        foreach ($sources as $k => $list) {
            $sources[$k] = $this->uniqueSorted($list);
        }

        return ['keys' => $keys, 'sources' => $sources];
    }

    /**
     * Собирает ключи из классов App\Settings\*, которые наследуются от DynamicConfigModel:
     *  - prefix() + fields() => ['a','b',...]
     *  - ключ: prefix.field или field
     *
     * @return array{keys: string[], sources: array<string, string[]>}
     */
    private function collectFromAppSettingsDynamicModels(): array
    {
        $dir = app_path('Settings');

        if (!is_dir($dir)) {
            return ['keys' => [], 'sources' => []];
        }

        $finder = (new Finder())
            ->files()
            ->in($dir)
            ->name('*.php');

        $keys = [];
        $sources = [];

        foreach ($finder as $file) {
            $path = $file->getRealPath() ?: $file->getPathname();
            $content = File::get($path);

            $class = $this->resolveClassFromFile($content);
            if ($class === null) {
                continue;
            }

            if (!class_exists($class)) {
                continue;
            }

            if (!is_subclass_of($class, DynamicConfigModel::class)) {
                continue;
            }

            try {
                $ref = new ReflectionClass($class);
                $instance = $ref->newInstanceWithoutConstructor();

                $prefix = $this->safeInvokeString($ref, $instance, 'prefix');
                $prefix = $prefix !== '' ? $prefix : null;

                $fields = $this->safeInvokeArray($ref, $instance, 'fields');
                if ($fields === null) {
                    continue;
                }

                foreach ($fields as $field) {
                    if (!is_string($field) || trim($field) === '') {
                        continue;
                    }

                    $field = trim($field);
                    $key = $prefix ? "{$prefix}.{$field}" : $field;

                    $keys[] = $key;
                    $sources[$key][] = $path;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        $keys = array_values(array_unique($keys));
        sort($keys);

        foreach ($sources as $k => $list) {
            $sources[$k] = $this->uniqueSorted($list);
        }

        return ['keys' => $keys, 'sources' => $sources];
    }

    private function safeInvokeString(ReflectionClass $ref, object $instance, string $method): string
    {
        if (!$ref->hasMethod($method)) {
            return '';
        }

        $m = $ref->getMethod($method);
        if (!($m->isPublic() || $m->isProtected())) {
            return '';
        }

        $m->setAccessible(true);
        $value = $m->invoke($instance);

        return is_string($value) ? trim($value) : '';
    }

    /**
     * @return array<int, mixed>|null
     */
    private function safeInvokeArray(ReflectionClass $ref, object $instance, string $method): ?array
    {
        if (!$ref->hasMethod($method)) {
            return null;
        }

        $m = $ref->getMethod($method);
        if (!($m->isPublic() || $m->isProtected())) {
            return null;
        }

        $m->setAccessible(true);
        $value = $m->invoke($instance);

        return is_array($value) ? $value : null;
    }

    /**
     * Достаёт FQCN из PHP-файла (namespace + class/final class).
     */
    private function resolveClassFromFile(string $content): ?string
    {
        if (!preg_match('/namespace\s+([^;]+);/m', $content, $ns)) {
            return null;
        }

        if (!preg_match('/\b(?:final\s+)?class\s+([A-Za-z0-9_]+)/m', $content, $cl)) {
            return null;
        }

        return trim($ns[1]) . '\\' . trim($cl[1]);
    }

    /**
     * Достаёт содержимое массива из protected $<name> = [ ... ];
     *
     * @return string[] blocks (content inside brackets)
     */
    private function extractProtectedArrayBlocks(string $content, string $propertyName): array
    {
        $pattern = '/protected\s+(?:array\s+)?\$(?:' . preg_quote($propertyName, '/') . ')\s*=\s*\[/m';

        if (!preg_match_all($pattern, $content, $m, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $blocks = [];

        foreach ($m[0] as $match) {
            $start = $match[1];

            $pos = strpos($content, '[', $start);
            if ($pos === false) {
                continue;
            }

            [$block] = $this->readBalancedBrackets($content, $pos);
            if ($block !== null) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    /**
     * Читает сбалансированные квадратные скобки начиная с позиции "[".
     * Возвращает (contentInsideBrackets, endPos).
     *
     * @return array{0: string|null, 1: int|null}
     */
    private function readBalancedBrackets(string $content, int $openPos): array
    {
        $len = strlen($content);
        $depth = 0;

        for ($i = $openPos; $i < $len; $i++) {
            $ch = $content[$i];

            if ($ch === "'" || $ch === '"') {
                [, $next] = $this->readQuotedString($content, $i);
                $i = $next - 1;
                continue;
            }

            if ($ch === '[') {
                $depth++;
                continue;
            }

            if ($ch === ']') {
                $depth--;
                if ($depth === 0) {
                    $inside = substr($content, $openPos + 1, $i - ($openPos + 1));
                    return [$inside, $i];
                }
            }
        }

        return [null, null];
    }

    /**
     * Достаёт ключи ассоц. массива вида 'key' => ... / "key" => ...
     *
     * @return string[]
     */
    private function extractAssocArrayKeys(string $arrayContent): array
    {
        $keys = [];

        if (preg_match_all('/[\'"]([^\'"]+)[\'"]\s*=>/m', $arrayContent, $m)) {
            foreach ($m[1] as $k) {
                $k = trim($k);
                if ($k !== '') {
                    $keys[] = $k;
                }
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param string[] $a
     * @param string[] $b
     * @return string[]
     */
    private function uniqueMerge(array $a, array $b): array
    {
        return array_values(array_unique(array_merge($a, $b)));
    }

    /**
     * @param string[] $list
     * @return string[]
     */
    private function uniqueSorted(array $list): array
    {
        $list = array_values(array_unique($list));
        sort($list);
        return $list;
    }
}
