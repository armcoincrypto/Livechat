<?php

declare(strict_types=1);

namespace App\Settings;

use iEXPackages\DynamicConfig\DynamicConfigModel;

/**
 * BannerConfig
 *
 * Обёртка над DynamicConfig для настроек баннеров iEXExchanger.
 *
 * Scope:
 *   - scope_type = 'global'
 *   - scope_id   = null
 *
 * Ключи DynamicConfig:
 *   - banners.is_autoplay (bool)
 *   - banners.timeout     (int, секунды)
 *   - banners.hide_nav    (int)
 */
final class BannerConfig extends DynamicConfigModel
{
    /**
     * Префикс ключей для баннеров.
     *
     * Итого ключи:
     *   banners.is_autoplay
     *   banners.timeout
     *   banners.hide_nav
     */
    protected function prefix(): string
    {
        return 'banners';
    }

    /**
     * Returns the list of local field names managed by this config.
     */
    protected function fields(): array
    {
        return [
            'is_autoplay',
            'timeout',
            'hide_nav',
        ];
    }

    /**
     * Включено ли автопроигрывание баннеров.
     */
    public function isAutoplay(): bool
    {
        return $this->getBool('is_autoplay', false);
    }

    /**
     * Таймаут переключения баннеров (секунды).
     */
    public function timeout(): int
    {
        return $this->getInt('timeout', 0);
    }

    /**
     * Режим отображения навигации.
     */
    public function hideNav(): int
    {
        return $this->getInt('hide_nav', 0);
    }

}
