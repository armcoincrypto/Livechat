<?php

declare(strict_types=1);

namespace iEXPackages\Payment;

use App\Facades\Vault;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException;
use iEXPackages\Payment\Engines\GatewayFactory;
use iEXPackages\Payment\Engines\Interfaces\GatewayInterface;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class Payment
{
    /**
     * Экземпляр приложения.
     */
    protected Application $app;

    /**
     * Доступные адаптеры платежных систем
     */
    protected array $gateways = [];

    /**
     * Конфигурации по драйверам
     */
    protected array $configs = [];

    /**
     * Платежная фабрика
     */
    protected GatewayFactory $factory;

    /**
     * Конструктор биллинга
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->factory = new GatewayFactory;
    }

    /**
     * Установить конфигурацию вручную для конкретного драйвера
     *
     * @param array $config
     * @param string $driver
     * @return $this
     *
     * @example
     * $payment->setConfig($decryptedConfig, 'stripe');
     */
    public function setConfig(array $config, string $driver): self
    {
        $this->configs[Str::studly($driver)] = $config;
        return $this;
    }

    /**
     * Получаем конфигурацию платежных шлюзов
     */
    public function config(string $name): array
    {
        try {
            // Берём только config.json в подпапках Gateways и ищем по имени папки (case-insensitive)
            $files = File::allFiles(__DIR__ . '/Gateways');

            /** @var \Symfony\Component\Finder\SplFileInfo|null $configuration */
            $configuration = collect($files)->first(function (\Symfony\Component\Finder\SplFileInfo $file) use ($name) {
                if ($file->getFilename() !== 'config.json') {
                    return false;
                }
                // relativePath = имя подпапки шлюза (например, B2BWallet)
                return Str::lower($file->getRelativePath()) === Str::lower($name);
            });

            if (! $configuration) {
                Log::warning("Payment config not found for gateway: {$name}");
                return [];
            }

            return File::json($configuration->getRealPath());
        } catch (\Throwable $e) {
            Log::error("Payment config load error: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Получаем основные поля
     *
     * @param string $name
     * @return array
     */
    public function getFields(string $name): array
    {
        $response = $this->config($name);
        return $response['fields']['merchant'] ?? [];
    }

    /**
     * Получаем дополнительные поля
     */
    public function getOptionsFields(string $name)
    {
        $response = $this->config($name);
        return $response['options_fields']['merchant'] ?? [];
    }

    /**
     * Получение экземпляра платежного шлюза по указанному имени и файлу конфигурации.
     *
     * @param string $name
     * @param string $filename
     *
     * @return GatewayInterface
     *
     * @throws EnvironmentIsBrokenException
     * @throws WrongKeyOrModifiedCiphertextException
     * @throws InvalidArgumentException
     */
    public function merchant(string $name, string $filename): GatewayInterface
    {
        $driver = Str::studly($name);

        if (!isset($this->gateways[$driver])) {
            $this->gateways[$driver] = $this->resolve($driver, $filename);
        }

        return $this->gateways[$driver];
    }

    /**
     * Доступ к API платежным системам
     *
     * @param string $name
     * @param string $filename
     * @return mixed
     *
     * @throws EnvironmentIsBrokenException
     * @throws WrongKeyOrModifiedCiphertextException
     */
    public function pay(string $name, string $filename): mixed
    {
        $driver = Str::studly($name);
        return $this->gateways[$driver] = $this->get($driver, $filename);
    }

    /**
     * Попытайтесь получить платеж из локального кеша.
     *
     * @return mixed
     *
     * @throws EnvironmentIsBrokenException
     * @throws WrongKeyOrModifiedCiphertextException
     */
    protected function get(string $name, string $filename)
    {
        return $this->gateways[$name] ?? $this->resolve($name, $filename);
    }

    /**
     * Получение расположения по названию шлюза
     *
     * @return \iEXPackages\Payment\Engines\Interfaces\GatewayInterface|mixed
     */
    private function adapter($driver, $config)
    {
        if (! isset($this->gateways[$driver])) {
            $gateway = $this->factory->create($driver, null, $this->app['request']);
            $gateway->initialize($config);

            $this->gateways[$driver] = $gateway;
        }

        return $this->gateways[$driver];
    }

    /**
     * Resolve the given store.
     *
     * @return mixed
     *
     * @throws EnvironmentIsBrokenException
     * @throws WrongKeyOrModifiedCiphertextException
     */
    private function resolve(string $name, string $filename)
    {
        $driver = Str::studly($name);

        if (!empty($this->configs[$driver])) {
            $config = $this->configs[$driver];
            Log::debug("[Payment] Конфигурация для '$driver' использована из setConfig().");
        } else {
            $config = Vault::decryptFromFile($filename, 'gateways');
            $this->configs[$driver] = $config;
            Log::debug("[Payment] Конфигурация для '$driver' загружена из Vault.");
        }

        $providers = $this->getProviders();

        if (isset($providers[Str::lower($name)])) {
            $register_name = $providers[Str::lower($name)];
            return $this->adapter($register_name, $config);
        }

        throw new InvalidArgumentException("Driver [{$name}] is not supported.");
    }

    /**
     * Получаем все платежные шлюзы
     */
    private function getProviders(): array
    {
        $finder = Finder::create();

        $finder
            ->in(__DIR__.'/Gateways')
            ->depth(0)
            ->filter(static function (SplFileInfo $file) {
                return $file->isDir() || \preg_match('/\.(php|json)$/', $file->getFilename());
            });

        $providers = [];
        foreach (iterator_to_array($finder, true) as $item) {
            $providers[Str::lower($item->getFilename())] = $item->getFilename();
        }

        return $providers;
    }
}
