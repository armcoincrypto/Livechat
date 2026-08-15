<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Blacklist\DTO;

/**
 * Автор записи в базе BestChange.
 */
final readonly class BlacklistAuthor
{
    public function __construct(
        /** Название обменного пункта */
        public string $name,
        /** Ссылка на страницу обменного пункта на BestChange (может отсутствовать) */
        public ?string $url,
    ) {}

    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string)($data['name'] ?? ''),
            url: isset($data['url']) ? (string)$data['url'] : null,
        );
    }
}
