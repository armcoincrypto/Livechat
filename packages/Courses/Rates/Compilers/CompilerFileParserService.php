<?php
declare(strict_types=1);

namespace iEXPackages\Courses\Rates\Compilers;

use App\Models\FileParserGroup;
use iEXPackages\Courses\Services\FileParserService;
use iEXPackages\Courses\Services\RatesLoggerService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * CompilerFileParserService
 *
 * Назначение:
 * - Обновляет курсы из файловых источников (JSON/CSV/TXT), привязанных к FileParserGroup.
 *
 * Оптимизации:
 * 1) Eager loading file_parser_rates (убираем N+1)
 * 2) Уникальные URL (убираем повторные скачивания)
 * 3) Параллельная загрузка URL через Http::pool()
 * 4) Короткий кеш контента URL (Redis) на 20–60 сек
 * 5) Один upsert на группу (при необходимости — чанками)
 * 6) updated_at вычисляем один раз на группу
 *
 * Требование:
 * - FileParserService::parseFromContent(string $url, string $content): array
 */
final class CompilerFileParserService
{
    private const int POOL_CONCURRENCY = 10;
    private const int CONTENT_CACHE_TTL_SECONDS = 30;
    private const int MAX_FILE_SIZE = 2_097_152; // 2MB

    /**
     * Размер чанка upsert (если в группе очень много rates).
     */
    private const int UPSERT_CHUNK_SIZE = 1000;

    public function __construct(
        private readonly RatesLoggerService $logger,
        private readonly FileParserService $parserService,
    ) {}

    public function handle(): void
    {
        $groups = FileParserGroup::query()
            ->select(['id', 'name', 'link', 'sorting'])
            ->with(['file_parser_rates:id,id_group,name,summa'])
            ->orderBy('sorting')
            ->get();

        if ($groups->isEmpty()) {
            return;
        }

        $urls = $groups
            ->pluck('link')
            ->filter(fn ($v) => is_string($v) && trim($v) !== '')
            ->map(fn ($v) => trim((string) $v))
            ->unique()
            ->values()
            ->all();

        $contentsByUrl = $this->fetchContentsConcurrently($urls);

        foreach ($groups as $group) {
            $url = trim((string) $group->link);

            if ($url === '' || !array_key_exists($url, $contentsByUrl)) {
                continue;
            }

            $content = $contentsByUrl[$url];
            if (!is_string($content) || $content === '') {
                continue;
            }

            try {
                $rates = $this->parserService->parseFromContent($url, $content);
            } catch (Throwable $e) {
                Log::error('FileParser: ошибка парсинга контента', [
                    'group_id' => (int) $group->id,
                    'group' => (string) $group->name,
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            if ($rates === []) {
                continue;
            }

            $now = now();
            $updateData = [];

            foreach ($group->file_parser_rates as $row) {
                $pair = Str::upper(trim((string) $row->name));

                if (!isset($rates[$pair])) {
                    continue;
                }

                $newValue = (string) $rates[$pair];

                $updateData[] = [
                    'id' => (int) $row->id,
                    'value' => 1,
                    'summa' => $newValue,
                    'updated_at' => $now,
                ];

                $this->logger->batchLog(
                    'file',
                    (int) $row->id,
                    (string) $row->name,
                    (string) $row->summa,
                    $newValue
                );
            }

            if ($updateData === []) {
                continue;
            }

            // Если много строк — upsert чанками, чтобы не раздувать один SQL.
            foreach (array_chunk($updateData, self::UPSERT_CHUNK_SIZE) as $chunk) {
                DB::table('file_parser_rates')->upsert(
                    $chunk,
                    ['id'],
                    ['value', 'summa', 'updated_at']
                );
            }
        }

        $this->logger->flush();
    }

    /**
     * Параллельная загрузка контента файлов:
     * - читаем из кеша
     * - остальное скачиваем чанками по POOL_CONCURRENCY через Http::pool()
     *
     * @param array<int, string> $urls
     * @return array<string, string|null> url => content|null
     */
    private function fetchContentsConcurrently(array $urls): array
    {
        $result = [];
        if ($urls === []) {
            return $result;
        }

        $toFetch = [];

        foreach ($urls as $url) {
            $cacheKey = $this->contentCacheKey($url);

            $cached = Cache::get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                $result[$url] = $cached;
                continue;
            }

            $toFetch[] = $url;
        }

        if ($toFetch === []) {
            return $result;
        }

        // Скачиваем чанками, чтобы не создавать огромный пул, если URL много.
        foreach (array_chunk($toFetch, self::POOL_CONCURRENCY) as $batch) {
            $responses = Http::pool(function ($pool) use ($batch) {
                foreach ($batch as $url) {
                    $pool->as($url)
                        ->timeout(5)
                        ->retry(1, 150)
                        ->get($url);
                }
            });

            foreach ($batch as $url) {
                $resp = $responses[$url] ?? null;

                if (!$resp || !$resp->successful()) {
                    $result[$url] = null;
                    continue;
                }

                $body = trim((string) $resp->body());

                if ($body === '' || strlen($body) > self::MAX_FILE_SIZE) {
                    $result[$url] = null;
                    continue;
                }

                Cache::put(
                    $this->contentCacheKey($url),
                    $body,
                    self::CONTENT_CACHE_TTL_SECONDS
                );

                $result[$url] = $body;
            }
        }

        return $result;
    }

    private function contentCacheKey(string $url): string
    {
        return 'iex:courses:file_content:' . sha1($url);
    }
}
