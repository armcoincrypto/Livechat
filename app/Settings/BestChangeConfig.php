<?php

declare(strict_types=1);

namespace App\Settings;

use iEXPackages\DynamicConfig\DynamicConfigModel;

/**
 * BestChangeConfig
 *
 * Настройки модуля BestChange.
 *
 * Хранение:
 * - scope_type = 'plugins'
 * - scope_id   = null
 * - key        = bestchange.*
 *
 * Назначение:
 * - хранение всех ключевых параметров парсинга BestChange в DynamicConfig
 * - обеспечение единых геттеров/сеттера update() для контроллеров и сервисов
 *
 * Ключевые группы настроек:
 * - базовые: is_enable, api_key, timeout, interval, site_version, proxy_id
 * - выбор курса: type_position, position, rate_mode, top_n
 * - списки: currencies, cities, blacklist
 * - анти-фейк: anti_fake_enabled, anti_fake_min_score
 * - пул обменников: exchanger_pool_mode, exchanger_pool_preferred, exchanger_pool_excluded
 */
final class BestChangeConfig extends DynamicConfigModel
{
    /**
     * Префикс ключей конфига: bestchange.*
     */
    protected function prefix(): string
    {
        return 'bestchange';
    }

    /**
     * Настройки BestChange живут в scope_type = plugins.
     */
    protected function scopeType(): string
    {
        return 'plugins';
    }

    /**
     * Один набор настроек на всю установку.
     */
    protected function scopeId(): ?int
    {
        return null;
    }

    /**
     * Список полей, за которые отвечает этот конфиг.
     *
     * @return string[]
     */
    protected function fields(): array
    {
        return [
            // базовые
            'is_enable',
            'api_key',
            'timeout',
            'interval',
            'site_version',
            'proxy_id',
            'is_log_error',

            'type_position',
            'position',
            'rate_mode',
            'top_n',

            'currencies',
            'cities',
            'blacklist',

            'anti_fake_enabled',
            'anti_fake_min_score',

            'exchanger_pool_mode',
            'exchanger_pool_preferred',
            'exchanger_pool_excluded',


            'auto_blacklist_enabled',
            'auto_blacklist_default_minutes',
        ];
    }

    // ---------------------------------------------------------------------
    // Базовые настройки
    // ---------------------------------------------------------------------

    /**
     * Авто-блокировка обменников по поведению (cooldown).
     */
    public function autoBlacklistEnabled(): bool
    {
        return $this->getBool('auto_blacklist_enabled', true);
    }

    /**
     * Дефолтный срок блокировки (минуты), если в сервисе передали 0.
     */
    public function autoBlacklistDefaultMinutes(): int
    {
        return max(0, min(10_080, $this->getInt('auto_blacklist_default_minutes', 120))); // до 7 дней
    }

    public function isEnabled(): bool
    {
        return $this->getBool('is_enable', false);
    }

    public function apiKey(): string
    {
        return $this->getString('api_key', '');
    }

    public function timeout(): int
    {
        // разумный дефолт, чтобы не было "0"
        return max(1, $this->getInt('timeout', 10));
    }

    public function interval(): int
    {
        return max(0, $this->getInt('interval', 0));
    }

    public function siteVersion(): string
    {
        $v = strtolower(trim($this->getString('site_version', 'ru')));
        return preg_match('/^[a-z]{2,5}$/', $v) ? $v : 'en';
    }

    public function proxyId(): int
    {
        return max(0, $this->getInt('proxy_id', 0));
    }

    public function isLogError(): bool
    {
        return $this->getBool('is_log_error', false);
    }

    // ---------------------------------------------------------------------
    // Настройки выбора курса
    // ---------------------------------------------------------------------

    /**
     * Тип поля курса, используемого в расчёте:
     * - rate
     * - rankrate
     */
    public function typePosition(): string
    {
        $v = strtolower(trim($this->getString('type_position', 'rankrate')));
        return in_array($v, ['rate', 'rankrate'], true) ? $v : 'rankrate';
    }

    /**
     * Позиция по умолчанию (для режима position).
     */
    public function position(): int
    {
        return max(1, $this->getInt('position', 1));
    }

    /**
     * Режим выбора курса:
     * - position
     * - median_top_n
     * - weighted_avg_top_n
     */
    public function rateMode(): string
    {
        $v = strtolower(trim($this->getString('rate_mode', 'position')));
        return in_array($v, ['position', 'median_top_n', 'weighted_avg_top_n'], true) ? $v : 'position';
    }

    /**
     * Размер TOP-N для median/weighted.
     */
    public function topN(): int
    {
        return max(1, min(100, $this->getInt('top_n', 5)));
    }

    // ---------------------------------------------------------------------
    // Списки и фильтры
    // ---------------------------------------------------------------------

    /**
     * @return int[]
     */
    public function currencies(): array
    {
        $value = $this->getArray('currencies', []);
        return array_values(array_map('intval', (array) $value));
    }

    /**
     * @return int[]
     */
    public function cities(): array
    {
        $value = $this->getArray('cities', []);
        return array_values(array_map('intval', (array) $value));
    }

    /**
     * Глобальный blacklist обменников (ID).
     *
     * @return array<int,mixed>
     */
    public function blacklist(): array
    {
        return $this->getArray('blacklist', []);
    }


    // ---------------------------------------------------------------------
    // Anti-Fake
    // ---------------------------------------------------------------------

    public function antiFakeEnabled(): bool
    {
        return $this->getBool('anti_fake_enabled', true);
    }

    /**
     * Минимально допустимый score качества (0..100).
     */
    public function antiFakeMinScore(): int
    {
        return max(0, min(100, $this->getInt('anti_fake_min_score', 50)));
    }

    // ---------------------------------------------------------------------
    // Exchanger pool
    // ---------------------------------------------------------------------

    /**
     * Режим пула обменников:
     * - off   : не применять пул
     * - soft  : excluded исключаем, preferred помечаем/учитываем на уровне селектора
     * - strict: разрешаем только preferred (как whitelist)
     */
    public function exchangerPoolMode(): string
    {
        $v = strtolower(trim($this->getString('exchanger_pool_mode', 'off')));
        return in_array($v, ['off', 'soft', 'strict'], true) ? $v : 'off';
    }

    /**
     * @return int[]
     */
    public function exchangerPoolPreferred(): array
    {
        return array_values(array_map('intval', (array) $this->getArray('exchanger_pool_preferred', [])));
    }

    /**
     * @return int[]
     */
    public function exchangerPoolExcluded(): array
    {
        return array_values(array_map('intval', (array) $this->getArray('exchanger_pool_excluded', [])));
    }

    // ---------------------------------------------------------------------
    // Обновление
    // ---------------------------------------------------------------------

    /**
     * Массовое обновление настроек BestChange.
     *
     * Поддерживаемые ключи:
     * - базовые: is_enable, api_key, timeout, interval, site_version, proxy_id, is_log_error
     * - выбор курса: type_position, position, rate_mode, top_n
     * - списки: currencies, cities, blacklist
     * - anti-fake: anti_fake_enabled, anti_fake_min_score
     * - exchanger pool: exchanger_pool_mode, exchanger_pool_preferred, exchanger_pool_excluded
     *
     * @param array{
     *   is_enable?: bool|int,
     *   api_key?: string,
     *   timeout?: int,
     *   interval?: int,
     *   site_version?: string,
     *   proxy_id?: int,
     *   is_log_error?: bool|int,
     *
     *   type_position?: string,
     *   position?: int,
     *   rate_mode?: string,
     *   top_n?: int,
     *
     *   currencies?: array|mixed,
     *   cities?: array|mixed,
     *   blacklist?: array|mixed,
     *
     *   anti_fake_enabled?: bool|int,
     *   anti_fake_min_score?: int,
     *
     *   exchanger_pool_mode?: string,
     *   exchanger_pool_preferred?: array|mixed,
     *   exchanger_pool_excluded?: array|mixed
     * } $data
     */
    public function update(array $data): void
    {
        $normalized = [];

        if (array_key_exists('auto_blacklist_enabled', $data)) {
            $normalized['auto_blacklist_enabled'] = (bool) $data['auto_blacklist_enabled'];
        }

        if (array_key_exists('auto_blacklist_default_minutes', $data)) {
            $normalized['auto_blacklist_default_minutes'] = max(0, min(10_080, (int) $data['auto_blacklist_default_minutes']));
        }

        // --- выбор курса ---
        if (array_key_exists('type_position', $data)) {
            $v = strtolower(trim((string)$data['type_position']));
            $normalized['type_position'] = in_array($v, ['rate','rankrate'], true) ? $v : 'rankrate';
        }

        if (array_key_exists('position', $data)) {
            $normalized['position'] = max(1, (int) $data['position']);
        }

        if (array_key_exists('rate_mode', $data)) {
            $v = strtolower(trim((string)$data['rate_mode']));
            $normalized['rate_mode'] = in_array($v, ['position','median_top_n','weighted_avg_top_n'], true) ? $v : 'position';
        }

        if (array_key_exists('top_n', $data)) {
            $normalized['top_n'] = max(1, min(100, (int) $data['top_n']));
        }

        // --- anti-fake ---
        if (array_key_exists('anti_fake_enabled', $data)) {
            $normalized['anti_fake_enabled'] = (bool) $data['anti_fake_enabled'];
        }

        if (array_key_exists('anti_fake_min_score', $data)) {
            $normalized['anti_fake_min_score'] = max(0, min(100, (int) $data['anti_fake_min_score']));
        }

        // --- exchanger pool ---
        if (array_key_exists('exchanger_pool_mode', $data)) {
            $v = strtolower(trim((string)$data['exchanger_pool_mode']));
            $normalized['exchanger_pool_mode'] = in_array($v, ['off','soft','strict'], true) ? $v : 'off';
        }

        if (array_key_exists('exchanger_pool_preferred', $data)) {
            $value = $data['exchanger_pool_preferred'];
            if (is_string($value)) {
                $value = array_filter(array_map('trim', explode(',', $value)));
            }
            $normalized['exchanger_pool_preferred'] = array_values(array_map('intval', (array) $value));
        }

        if (array_key_exists('exchanger_pool_excluded', $data)) {
            $value = $data['exchanger_pool_excluded'];
            if (is_string($value)) {
                $value = array_filter(array_map('trim', explode(',', $value)));
            }
            $normalized['exchanger_pool_excluded'] = array_values(array_map('intval', (array) $value));
        }

        // --- базовые ---
        if (array_key_exists('is_enable', $data)) {
            $normalized['is_enable'] = (bool) $data['is_enable'];
        }

        if (array_key_exists('api_key', $data)) {
            $normalized['api_key'] = (string) $data['api_key'];
        }

        if (array_key_exists('timeout', $data)) {
            $normalized['timeout'] = max(1, (int) $data['timeout']);
        }

        if (array_key_exists('interval', $data)) {
            $normalized['interval'] = max(0, (int) $data['interval']);
        }

        if (array_key_exists('site_version', $data)) {
            $normalized['site_version'] = (string) $data['site_version'];
        }

        if (array_key_exists('proxy_id', $data)) {
            $normalized['proxy_id'] = max(0, (int) $data['proxy_id']);
        }

        if (array_key_exists('is_log_error', $data)) {
            $normalized['is_log_error'] = (bool) $data['is_log_error'];
        }

        // --- списки ---
        if (array_key_exists('currencies', $data)) {
            $value = $data['currencies'];
            if (is_string($value)) {
                $value = array_filter(array_map('trim', explode(',', $value)));
            }
            $normalized['currencies'] = array_values(array_map('intval', (array) $value));
        }

        if (array_key_exists('cities', $data)) {
            $value = $data['cities'];
            if (is_string($value)) {
                $value = array_filter(array_map('trim', explode(',', $value)));
            }
            $normalized['cities'] = array_values(array_map('intval', (array) $value));
        }

        if (array_key_exists('blacklist', $data)) {
            $normalized['blacklist'] = (array) $data['blacklist'];
        }

        parent::update($normalized);
    }

    /**
     * Выгрузка конфига в массив (для API/админки).
     */
    public function toArray(): array
    {
        return [
            // базовые
            'is_enable'      => $this->isEnabled(),
            'api_key'        => $this->apiKey(),
            'timeout'        => $this->timeout(),
            'interval'       => $this->interval(),
            'site_version'   => $this->siteVersion(),
            'proxy_id'       => $this->proxyId(),
            'is_log_error'   => $this->isLogError(),

            // выбор курса
            'type_position'  => $this->typePosition(),
            'position'       => $this->position(),
            'rate_mode'      => $this->rateMode(),
            'top_n'          => $this->topN(),

            // списки
            'currencies'     => $this->currencies(),
            'cities'         => $this->cities(),
            'blacklist'      => $this->blacklist(),

            // anti-fake
            'anti_fake_enabled'   => $this->antiFakeEnabled(),
            'anti_fake_min_score' => $this->antiFakeMinScore(),

            // exchanger pool
            'exchanger_pool_mode'      => $this->exchangerPoolMode(),
            'exchanger_pool_preferred' => $this->exchangerPoolPreferred(),
            'exchanger_pool_excluded'  => $this->exchangerPoolExcluded(),

            'auto_blacklist_enabled' => $this->autoBlacklistEnabled(),
            'auto_blacklist_default_minutes' => $this->autoBlacklistDefaultMinutes(),
        ];
    }
}
