<?php

declare(strict_types=1);

namespace App\Settings;

use iEXPackages\DynamicConfig\DynamicConfigModel;

/**
 * AdvantageConfig
 *
 * Обёртка над DynamicConfig для настроек блока "Преимущества".
 *
 * Ключи:
 *   - advantage.is_advantage_style   (bool)
 *   - advantage.advantage_row_height (string|null)
 *   - advantage.advantage_col        (int)
 *   - advantage.advantage_gutter_size(string|null)
 *
 * Scope: global (общие для всей системы).
 */
final class AdvantageConfig extends DynamicConfigModel
{
    protected function prefix(): string
    {
        return 'advantage';
    }

    /**
     * Включён ли кастомный стиль блока преимуществ.
     */
    public function isStyleEnabled(): bool
    {
        return $this->getBool('is_advantage_style', false);
    }

    /**
     * Высота строк (например "2:1").
     */
    public function rowHeight(): ?string
    {
        $value = $this->getString('advantage_row_height', '2:1');
        return $value !== '' ? $value : null;
    }

    /**
     * Количество колонок.
     */
    public function columns(): int
    {
        return $this->getInt('advantage_col', 3);
    }

    /**
     * Ширина отступов между элементами (например "10px").
     */
    public function gutterSize(): ?string
    {
        $value = $this->getString('advantage_gutter_size', '10px');
        return $value !== '' ? $value : null;
    }

    /**
     * Список полей advantage-конфига.
     */
    protected function fields(): array
    {
        return [
            'is_advantage_style',
            'advantage_row_height',
            'advantage_col',
            'advantage_gutter_size',
        ];
    }
}
