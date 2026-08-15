<?php

declare(strict_types=1);

namespace App\Support\Locales;

use InvalidArgumentException;

final readonly class LocaleDefinition
{
    /**
     * @param array{name:string,active:bool,field:string} $options
     */
    public function __construct(
        public int $sort,
        public string $name,
        public string $alias,
        public string $icon,
        public array $options,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, string $sourceFile): self
    {
        $sort  = self::int($data, 'sort', $sourceFile);
        $name  = self::string($data, 'name', $sourceFile);
        $alias = self::string($data, 'alias', $sourceFile);
        $icon  = self::string($data, 'icon', $sourceFile);

        $options = $data['options'] ?? null;
        if (!is_array($options)) {
            throw new InvalidArgumentException("Locales file [{$sourceFile}] key [options] must be an object.");
        }

        $optName   = self::string($options, 'name', $sourceFile, 'options');
        $optActive = self::bool($options, 'active', $sourceFile, 'options');
        $optField  = self::string($options, 'field', $sourceFile, 'options');

        return new self(
            sort: $sort,
            name: $name,
            alias: $alias,
            icon: $icon,
            options: [
                'name' => $optName,
                'active' => $optActive,
                'field' => $optField,
            ],
        );
    }

    public function isActive(): bool
    {
        return $this->options['active'] ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sort' => $this->sort,
            'name' => $this->name,
            'alias' => $this->alias,
            'icon' => $this->icon,
            'options' => $this->options,
        ];
    }

    /** @param array<string, mixed> $data */
    private static function string(array $data, string $key, string $file, ?string $prefix = null): string
    {
        $path = $prefix ? "{$prefix}.{$key}" : $key;
        $value = $data[$key] ?? null;

        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("Locales file [{$file}] key [{$path}] must be a non-empty string.");
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private static function int(array $data, string $key, string $file, ?string $prefix = null): int
    {
        $path = $prefix ? "{$prefix}.{$key}" : $key;
        $value = $data[$key] ?? null;

        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new InvalidArgumentException("Locales file [{$file}] key [{$path}] must be an integer.");
        }

        return (int) $value;
    }

    /** @param array<string, mixed> $data */
    private static function bool(array $data, string $key, string $file, ?string $prefix = null): bool
    {
        $path = $prefix ? "{$prefix}.{$key}" : $key;
        $value = $data[$key] ?? null;

        if (!is_bool($value)) {
            throw new InvalidArgumentException("Locales file [{$file}] key [{$path}] must be a boolean.");
        }

        return $value;
    }
}
