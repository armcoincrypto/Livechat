<?php

declare(strict_types=1);

namespace App\Support\Locales;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;

final class LanguageCatalog
{
    private const CACHE_KEY = 'iexexchanger.locales.catalog.v1';

    public function __construct(
        private readonly Filesystem $files,
        private readonly ?CacheRepository $cache = null,
        private readonly string $relativePath = 'app/iexexchanger/locales', // storage/{relativePath}
        private readonly int $cacheTtlSeconds = 300,
    ) {}

    /**
     * @return Collection<string, LocaleDefinition> key = alias
     */
    public function all(): Collection
    {
        return $this->remember(fn () => $this->load());
    }

    /** @return array<string, string> alias => name */
    public function onlyCodes(): array
    {
        return $this->all()
            ->mapWithKeys(fn (LocaleDefinition $l) => [$l->alias => $l->name])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function formOptions(): array
    {
        return $this->all()
            ->mapWithKeys(fn (LocaleDefinition $l) => [$l->alias => $l->toArray()])
            ->all();
    }

    public function find(string $alias): ?LocaleDefinition
    {
        $alias = trim($alias);
        if ($alias === '') {
            return null;
        }

        /** @var LocaleDefinition|null $locale */
        $locale = $this->all()->get($alias);

        return $locale;
    }

    public function active(): ?LocaleDefinition
    {
        /** @var LocaleDefinition|null $active */
        $active = $this->all()->first(fn (LocaleDefinition $l) => $l->isActive());

        return $active;
    }

    public function clearCache(): void
    {
        if ($this->cache) {
            $this->cache->forget(self::CACHE_KEY);
        }
    }

    /**
     * @return Collection<string, LocaleDefinition>
     */
    private function load(): Collection
    {
        $dir = storage_path($this->relativePath);

        if (!$this->files->isDirectory($dir)) {
            throw new RuntimeException("Locales directory not found: {$dir}");
        }

        $files = $this->files->files($dir);

        $items = collect($files)
            ->filter(fn ($f) => str_ends_with($f->getFilename(), '.json'))
            ->map(function ($file) {
                $filename = $file->getFilename();
                $raw = $file->getContents();

                try {
                    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    throw new RuntimeException("Locales file [{$filename}] contains invalid JSON: {$e->getMessage()}", 0, $e);
                }

                if (!is_array($decoded)) {
                    throw new InvalidArgumentException("Locales file [{$filename}] must contain a JSON object.");
                }

                return LocaleDefinition::fromArray($decoded, $filename);
            })
            ->values();

        $duplicates = $items
            ->groupBy(fn (LocaleDefinition $l) => $l->alias)
            ->filter(fn ($g) => $g->count() > 1)
            ->keys()
            ->values();

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException('Locales aliases must be unique. Duplicates: ' . $duplicates->implode(', '));
        }

        return $items
            ->sortBy(fn (LocaleDefinition $l) => $l->sort)
            ->keyBy(fn (LocaleDefinition $l) => $l->alias);
    }

    /**
     * @template T
     * @param \Closure():T $callback
     * @return T
     */
    private function remember(\Closure $callback): mixed
    {
        if (!$this->cache) {
            return $callback();
        }

        return $this->cache->remember(self::CACHE_KEY, $this->cacheTtlSeconds, $callback);
    }
}
