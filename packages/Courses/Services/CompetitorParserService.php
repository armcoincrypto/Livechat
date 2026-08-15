<?php

namespace iEXPackages\Courses\Services;

use iEXPackages\Calculator\Traits\InteractsWithNumbers;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;
use Exception;

class CompetitorParserService
{
    use InteractsWithNumbers;

    protected string $url;
    protected int $decimalPlaces;

    public function __construct(string $url, int $decimalPlaces = 18)
    {
        $this->url = $url;
        $this->decimalPlaces = min(max($decimalPlaces, 0), 18); // Ограничиваем от 0 до 18 знаков
    }

    /**
     * Получает все валютные пары в формате "КОД - ЗНАЧЕНИЕ"
     */
    public function getAllRates(): array
    {
        return $this->fetchRates()
            ->mapWithKeys(fn($rate) => ["{$rate['from']} - {$rate['to']}" => $rate['rate']])
            ->toArray();
    }

    /**
     * Загружает XML и проверяет валидность
     */
    private function fetchRates(): Collection
    {
        try {
            $response = Http::retry(3, 100)->get($this->url);

            if ($response->status() !== 200) {
                Log::error("Ошибка HTTP {$response->status()} при загрузке XML", ['url' => $this->url]);
                return collect();
            }

            $content = $response->body();

            if (empty($content)) {
                Log::error("Ошибка: пустой XML-файл", ['url' => $this->url]);
                return collect();
            }

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($content);

            if (!$xml) {
                Log::error("Ошибка при разборе XML", ['errors' => libxml_get_errors()]);
                return collect();
            }

            // Проверяем, содержит ли XML элементы <item>
            $items = $xml->xpath('//item');
            if (count($items) === 0) {
                Log::error("Ошибка: элементы <item> не найдены", ['xml' => $content]);
                return collect();
            }

            return $this->parseXml($items);
        } catch (Exception $e) {
            Log::error("Ошибка при загрузке XML", ['exception' => $e->getMessage()]);
            return collect();
        }
    }

    /**
     * Потоковая обработка XML (оптимизированная)
     */
    private function parseXml(array $items): Collection
    {
        return collect($items)->map(function ($item) {
            if (!isset($item->from, $item->to, $item->in, $item->out)) {
                return null;
            }

            $from = strtoupper(trim((string) $item->from));
            $to = strtoupper(trim((string) $item->to));
            $in = $this->sanitizeNumber((string) $item->in);
            $out = $this->sanitizeNumber((string) $item->out);

            if (!$this->isValidCurrencyCode($from) || !$this->isValidCurrencyCode($to) || $in === '0') {
                return null;
            }

            // Высокоточный расчет курса
            $rate = bcdiv($out, $in, $this->decimalPlaces);

            return [
                'from' => $from,
                'to' => $to,
                'rate' => $rate,
            ];
        })->filter()->values();
    }

    /**
     * Проверяет, что код валюты содержит только буквы, цифры, _ или -
     */
    private function isValidCurrencyCode(string $code): bool
    {
        return preg_match('/^[A-Z0-9_-]+$/', $code);
    }
}
