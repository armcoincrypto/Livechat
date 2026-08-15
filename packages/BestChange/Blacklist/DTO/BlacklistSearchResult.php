<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Blacklist\DTO;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Результат поиска по базе BestChange.
 *
 * Важно:
 * - Этот объект НЕ выполняет HTTP-запросы.
 * - Это чистый DTO, удобный для логики, UI и тестов.
 *
 * @implements Arrayable<string,mixed>
 */
final readonly class BlacklistSearchResult implements Arrayable
{
    /**
     * @param BlacklistEntry[] $entries
     */
    public function __construct(
        /** Фактический query, который обработал сервис */
        public string $query,
        /** Количество найденных записей */
        public int $totalFound,
        /** Список найденных записей */
        public array $entries,
    ) {}

    /**
     * Есть ли совпадения.
     */
    public function hasMatches(): bool
    {
        return $this->totalFound > 0;
    }

    /**
     * Приведение к массиву (для JSON / логики API).
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'query' => $this->query,
            'total_found' => $this->totalFound,
            'entries' => array_map(static function (BlacklistEntry $e): array {
                return [
                    'id' => $e->id,
                    'type' => $e->type,
                    'date' => $e->date,
                    'author' => [
                        'name' => $e->author->name,
                        'url' => $e->author->url,
                    ],
                    'contacts' => $e->contacts,
                    'description' => $e->description,
                ];
            }, $this->entries),
        ];
    }
}
