<?php

namespace iEXPackages\AMLPlugin;

use App\Facades\Vault;
use App\Models\AMLService;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException;
use iEXPackages\AMLPlugin\Contracts\AMLDriverInterface;
use iEXPackages\AMLPlugin\Contracts\AMLResponseInterface;
use iEXPackages\AMLPlugin\Exceptions\AMLDriverException;
use iEXPackages\AMLPlugin\Helpers\AMLConfigLoader;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Класс AMLManager
 *
 * Управляет взаимодействием с AML-драйверами, обеспечивает загрузку и обработку запросов.
 *
 * @package iEXPackages\AMLPlugin
 */
class AMLManager
{
    /**
     * Текущий активный AML-драйвер.
     *
     * @var AMLDriverInterface
     */
    protected AMLDriverInterface $driver;

    /**
     * Зарегистрированные драйверы AML.
     *
     * @var array
     */
    protected static array $drivers = [];

    /**
     * Использовать ли кеширование.
     *
     * @var bool
     */
    protected bool $useCache = false;

    /**
     * Время жизни кеша (секунды).
     *
     * @var int
     */
    protected int $cacheTtl = 3600;

    /**
     * Регистрация нового драйвера AML.
     *
     * @param string $name Название драйвера
     * @param string $class Полное имя класса драйвера
     *
     * @return void
     */
    public static function registerDriver(string $name, string $class): void
    {
        static::$drivers[$name] = $class;
    }

    /**
     * Устанавливает активный драйвер и загружает конфигурации.
     *
     * @param string $driverName Название драйвера
     * @param AMLService|null $service Модель сервиса AML (опционально)
     *
     * @return self
     *
     * @throws EnvironmentIsBrokenException
     * @throws WrongKeyOrModifiedCiphertextException
     */
    public function driver(string $driverName, AMLService $service = null): self
    {
        $driverClass = static::$drivers[$driverName] ?? null;

        if (!$driverClass || !class_exists($driverClass)) {
            throw new InvalidArgumentException("AML driver [{$driverName}] not found.");
        }

        $defaultConfig = AMLConfigLoader::load($driverName);
        $protectedConfig = $service ? Vault::decryptFromFile($service->filename, 'aml') : [];

        $this->driver = App::make($driverClass, [
            'config' => $defaultConfig,
            'protectedConfig' => $protectedConfig,
            'service' => $service
        ]);

        return $this;
    }

    /**
     * Включает или отключает кеширование.
     *
     * @param bool $useCache Включить ли кеширование
     * @param int $ttl Время жизни кеша (секунды)
     *
     * @return self
     */
    public function useCache(bool $useCache = true, int $ttl = 3600): self
    {
        $this->useCache = $useCache;
        $this->cacheTtl = $ttl;

        return $this;
    }

    /**
     * Проверяет транзакцию через выбранный AML-драйвер.
     *
     * @param array $params Параметры проверки транзакции
     *
     * @return AMLResponseInterface
     *
     * @throws AMLDriverException
     */
    public function checkTransaction(array $params): AMLResponseInterface
    {
        return $this->execute('transaction', $params);
    }

    /**
     * Проверяет адрес через выбранный AML-драйвер.
     *
     * @param array $params Параметры проверки адреса
     *
     * @return AMLResponseInterface
     *
     * @throws AMLDriverException
     */
    public function checkAddress(array $params): AMLResponseInterface
    {
        return $this->execute('address', $params);
    }

    /**
     * Выполняет запрос к драйверу и обрабатывает кеширование и ошибки.
     *
     * @param string $type Тип проверки ('transaction' или 'address')
     * @param array $params Параметры запроса
     *
     * @return AMLResponseInterface
     *
     * @throws AMLDriverException
     */
    protected function execute(string $type, array $params): AMLResponseInterface
    {
        $cacheKey = $this->generateCacheKey($type, $params);

        try {
            if ($this->useCache) {
                return Cache::remember($cacheKey, $this->cacheTtl, fn() => $this->driver->{"check".ucfirst($type)}($params));
            }

            return $this->driver->{"check".ucfirst($type)}($params);

        } catch (Throwable $e) {
            Log::error("AML Check failed: {$e->getMessage()}", ['type' => $type, 'params' => $params]);
            throw new AMLDriverException("AML Check failed: {$e->getMessage()}", $e->getCode(), $e);
        }
    }

    /**
     * Генерирует уникальный ключ для кеширования запроса.
     *
     * @param string $type Тип проверки
     * @param array $params Параметры запроса
     *
     * @return string
     */
    protected function generateCacheKey(string $type, array $params): string
    {
        return sprintf(
            'aml:%s:%s:%s',
            class_basename($this->driver),
            $type,
            md5(json_encode($params))
        );
    }
}
