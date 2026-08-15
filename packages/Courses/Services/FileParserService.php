<?php
declare(strict_types=1);

namespace iEXPackages\Courses\Services;

use iEXPackages\Courses\ExpressionFunction\CustomExpressionFunctionProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Throwable;

/**
 * FileParserService
 *
 * Сервис парсинга курсов из внешних файлов (JSON/CSV/TXT).
 *
 * Ключевая оптимизация:
 * - Разделяем "скачивание" и "парсинг":
 *   - parse($url) скачивает файл и вызывает parseFromContent()
 *   - parseFromContent($url, $content) парсит уже скачанный контент (без HTTP)
 *
 * Это нужно для ускорения:
 * - компилятор скачивает файлы параллельно через Http::pool()
 * - и передаёт контент сюда, не делая повторных Http::get() внутри сервиса
 */
final class FileParserService
{
    private const int MAX_FILE_SIZE = 2_097_152; // 2 MB
    private const int MAX_ITERATIONS = 1000;
    private const int PRECISION = 18;

    private static ?ExpressionLanguage $language = null;

    /**
     * Парсинг по URL (совместимость).
     * Важно: делает HTTP запрос.
     *
     * @return array<string, string>
     */
    public function parse(string $url): array
    {
        $content = $this->fetchAndValidateFile($url);
        if ($content === null) {
            return [];
        }

        return $this->parseFromContent($url, $content);
    }

    /**
     * Парсинг уже скачанного контента (без сети).
     *
     * @param string $url     Нужен, чтобы определить расширение (json/csv/txt)
     * @param string $content Содержимое файла
     * @return array<string, string>
     */
    public function parseFromContent(string $url, string $content): array
    {
        $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        return match ($extension) {
            'json' => $this->parseJsonContent($content),
            'csv'  => $this->parseCsvContent($content),
            default => $this->parseTxtContent($content),
        };
    }

    /**
     * @return array<string, string>
     */
    private function parseJsonContent(string $content): array
    {
        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return [];
        }

        $rates = [];

        foreach ($decoded as $pair => $rate) {
            $pair = strtoupper(trim((string) $pair));
            $rate = trim((string) $rate);

            if ($pair === '' || $rate === '') {
                continue;
            }

            if (!$this->isValidCurrencyFormat("{$pair} : {$rate}")) {
                continue;
            }

            $rates[$pair] = $this->evaluateExpression($rate, $rates);
        }

        return $rates;
    }

    /**
     * @return array<string, string>
     */
    private function parseCsvContent(string $content): array
    {
        $lines = explode("\n", trim($content));
        $rates = [];

        foreach ($lines as $line) {
            $row = str_getcsv($line);

            if (count($row) < 2) {
                continue;
            }

            [$pair, $rate] = $row;

            $pair = strtoupper(trim((string) $pair));
            $rate = trim((string) $rate);

            if ($pair === '' || $rate === '') {
                continue;
            }

            if (!$this->isValidCurrencyFormat("{$pair} : {$rate}")) {
                continue;
            }

            $rates[$pair] = $this->evaluateExpression($rate, $rates);
        }

        return $rates;
    }

    /**
     * TXT поддерживает:
     * - алиасы: AAA = BTC
     * - несколько пар: USD - RUB, EUR - RUB : 98.12
     * - выражения с зависимостями: (USD - RUB) * 1.01
     *
     * @return array<string, string>
     */
    private function parseTxtContent(string $content): array
    {
        // Нормализуем "AAA - BBB :" к единому виду
        $content = preg_replace_callback(
            '/([A-Z][A-Z0-9_]{2,9})\s*-\s*([A-Z][A-Z0-9_]{2,9})\s*:\s*/i',
            fn ($m) => strtoupper($m[1]) . ' - ' . strtoupper($m[2]) . ' : ',
            $content
        );

        /** @var array<string, string> $aliases */
        $aliases = [];

        /** @var array<string, string> $rates */
        $rates = [];

        /**
         * @var array<string, array{expression:string, attempts:int}>
         */
        $delayed = [];

        $lines = explode("\n", trim($content));

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            // Алиасы
            if (str_contains($line, '=')) {
                [$alias, $original] = explode('=', $line, 2);
                $aliases[trim($alias)] = trim($original);
                continue;
            }

            // Ожидаем формат: pairsPart : rate
            [$pairsPart, $rate] = explode(':', $line, 2) + [null, null];
            if ($rate === null) {
                continue;
            }

            $pairsPart = (string) $pairsPart;
            $rate = trim($rate);

            foreach (explode(',', $pairsPart) as $pair) {
                $pair = strtoupper(trim($pair));
                if ($pair === '') {
                    continue;
                }

                $ratePrepared = $rate;

                // Применяем алиасы к паре и выражению
                foreach ($aliases as $a => $o) {
                    $pair = str_replace($a, $o, $pair);
                    $ratePrepared = str_replace($a, $o, $ratePrepared);
                }

                if (!$this->isValidCurrencyFormat("{$pair} : {$ratePrepared}")) {
                    continue;
                }

                // Отложенные выражения (зависят от других пар)
                if (preg_match('/\((.*?)\)/', $ratePrepared)) {
                    $delayed[$pair] = ['expression' => $ratePrepared, 'attempts' => 0];
                    continue;
                }

                $rates[$pair] = $this->evaluateExpression($ratePrepared, $rates);
            }
        }

        // Разрешаем зависимости (ограниченно)
        for ($i = 0; $i < self::MAX_ITERATIONS && $delayed !== []; $i++) {
            $remaining = $delayed;
            $delayed = [];

            foreach ($remaining as $pair => $data) {
                $attempts = $data['attempts'] + 1;

                try {
                    $rates[$pair] = $this->evaluateExpression($data['expression'], $rates);
                } catch (Throwable) {
                    if ($attempts < self::MAX_ITERATIONS) {
                        $delayed[$pair] = [
                            'expression' => $data['expression'],
                            'attempts' => $attempts,
                        ];
                    }
                }
            }
        }

        return $rates;
    }

    /**
     * Скачивание и базовая валидация файла (для parse()).
     */
    private function fetchAndValidateFile(string $url): ?string
    {
        try {
            $response = Http::timeout(5)->get($url);

            if (!$response->successful()) {
                return null;
            }

            $body = (string) $response->body();

            if ($body === '' || strlen($body) > self::MAX_FILE_SIZE) {
                return null;
            }

            if (!mb_check_encoding($body, 'UTF-8')) {
                return null;
            }

            // Убираем мусорные символы, оставляем printable + \n
            return trim((string) preg_replace('/[^\x20-\x7E\x0A]/', '', $body));
        } catch (Throwable $e) {
            Log::warning("Ошибка загрузки файла {$url}: {$e->getMessage()}");
            return null;
        }
    }

    private function isValidCurrencyFormat(string $line): bool
    {
        return (bool) preg_match(
            '/^[A-Z][A-Z0-9_]{2,9} - [A-Z][A-Z0-9_]{2,9} : ([-+*\/0-9().\s%]+|\(.*?\))$/',
            $line
        );
    }

    /**
     * Singleton ExpressionLanguage.
     */
    private static function language(): ExpressionLanguage
    {
        if (self::$language instanceof ExpressionLanguage) {
            return self::$language;
        }

        $language = new ExpressionLanguage();
        $language->registerProvider(new CustomExpressionFunctionProvider());

        self::$language = $language;

        return $language;
    }

    /**
     * Вычисление выражения с подстановкой уже рассчитанных пар.
     *
     * @param array<string, string> $rates
     */
    private function evaluateExpression(string $expression, array $rates): string
    {
        $language = self::language();

        $prepared = $this->replaceVariables($expression, $rates);

        // Проценты: 5% => 0.05
        $prepared = preg_replace_callback(
            '/(\d+(\.\d+)?)%/',
            fn ($m) => bcdiv($m[1], '100', self::PRECISION),
            $prepared
        );

        try {
            return (string) $language->evaluate($prepared);
        } catch (Throwable $e) {
            Log::error("Ошибка вычисления выражения '{$expression}': {$e->getMessage()}");
            return '0';
        }
    }

    /**
     * Подстановка:
     * - (PAIR) => 1/value
     * - PAIR   => value
     *
     * @param array<string, string> $rates
     */
    private function replaceVariables(string $expression, array $rates): string
    {
        foreach ($rates as $key => $value) {
            $value = ($value !== '' ? $value : '0');

            if (str_contains($expression, "({$key})")) {
                $denom = ($value === '0') ? '1' : $value;
                $inverse = bcdiv('1', $denom, self::PRECISION);
                $expression = str_replace("({$key})", $inverse, $expression);
            }

            $expression = str_replace((string) $key, (string) $value, $expression);
        }

        return $expression;
    }
}
