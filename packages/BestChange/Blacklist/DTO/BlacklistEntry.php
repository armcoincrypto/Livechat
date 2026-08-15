<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Blacklist\DTO;

/**
 * Одна запись из базы BestChange (мошенник/неадекват).
 */
final readonly class BlacklistEntry
{
    public function __construct(
        /** ID записи в базе BestChange */
        public string $id,
        /** Тип записи (как отдаёт API: "scam" / "inadequate") */
        public string $type,
        /** Дата добавления (строкой как в API) */
        public string $date,
        /** Автор записи */
        public BlacklistAuthor $author,
        /** Контакты/кошельки, по которым сработал поиск */
        public string $contacts,
        /** Описание причины */
        public string $description,
    ) {}

    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string)($data['id'] ?? ''),
            type: (string)($data['type'] ?? ''),
            date: (string)($data['date'] ?? ''),
            author: BlacklistAuthor::fromArray((array)($data['author'] ?? [])),
            contacts: (string)($data['contacts'] ?? ''),
            description: (string)($data['desc'] ?? ''),
        );
    }
}
