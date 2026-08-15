<?php

namespace iEXPackages\AMLPlugin\Drivers\Shard;

use App\Models\AMLService;
use iEXPackages\AMLPlugin\Contracts\AMLDriverInterface;
use iEXPackages\AMLPlugin\Contracts\AMLResponseInterface;
use iEXPackages\AMLPlugin\Exceptions\AMLDriverException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Random\RandomException;

/**
 * Класс ShardDriver
 *
 * Реализует взаимодействие с AML-сервисом Shard, предоставляя методы
 * для проверки транзакций и адресов с использованием JWT-авторизации.
 *
 * @package iEXPackages\AMLPlugin\Drivers\Rapira
 */
class ShardDriver implements AMLDriverInterface
{
    /**
     * Общая конфигурация драйвера.
     *
     * @var array
     */
    protected array $config;

    /**
     * Защищённая конфигурация драйвера (ключи, секреты и т.д.).
     *
     * @var array
     */
    protected array $protectedConfig;

    /**
     * Модель сервиса AML (необязательно).
     *
     * @var AMLService|null
     */
    protected ?AMLService $service;

    /**
     * Конструктор драйвера ShardDriver.
     *
     * @param array            $config          Общая конфигурация
     * @param array            $protectedConfig Защищённая конфигурация
     * @param AMLService|null  $service         Модель AML-сервиса
     */
    public function __construct(array $config, array $protectedConfig = [], AMLService $service = null)
    {
        $this->config = $config;
        $this->protectedConfig = $protectedConfig;
        $this->service = $service;
    }

    /**
     * Выполняет проверку транзакции через API.
     *
     * @param array $params ['tx', 'address', 'currency']
     *
     * @return AMLResponseInterface
     *
     * @throws AMLDriverException
     * @throws ConnectionException
     * @throws RandomException
     * @throws \DateMalformedStringException
     */
    public function checkTransaction(array $params): AMLResponseInterface
    {
        $hash = $params['tx'] ?? '';
        $currencyTag = $this->resolveCurrency($params['currency'] ?? '');
        $resultResponse = $this->callRequest('/transaction/'. $hash .'/risks/' . $currencyTag);

        return new ShardResponse($resultResponse, $this->protectedConfig);
    }

    /**
     * Выполняет проверку адреса через API.
     *
     * @param array $params ['address', 'currency']
     *
     * @return AMLResponseInterface
     *
     * @throws AMLDriverException
     * @throws ConnectionException
     * @throws RandomException
     * @throws \DateMalformedStringException
     */
    public function checkAddress(array $params): AMLResponseInterface
    {
        $hash = $params['address'] ?? '';
        $currencyTag = $this->resolveCurrency($params['currency'] ?? '');
        $resultResponse = $this->callRequest('/v2/address/'. $hash .'/risks/' . $currencyTag);

        return new ShardResponse($resultResponse, $this->protectedConfig);
    }

    /**
     * Отправляет защищённый запрос к Shard AML API.
     *
     * @param string $path Путь API
     *
     * @return array Ответ API
     *
     * @throws AMLDriverException Если произошла ошибка запроса или ответа
     * @throws ConnectionException Если произошла ошибка запроса или ответа
     * @throws RandomException
     * @throws \DateMalformedStringException
     */
    private function callRequest(string $path): array
    {
        $apiHost = 'https://shard.ru/external/api';

        $response = Http::baseUrl($apiHost)
            ->get($path, [
                'token' => $this->protectedConfig['api_key']
            ]);

        $data = $response->json();

        if ($response->failed()) {
            throw new AMLDriverException('Ошибка проверки AML (' . $path . '): ' . json_encode($data, JSON_UNESCAPED_UNICODE));
        }

        return $data;
    }

    /**
     * Приводит валюту к формату, совместимому с Rapira API.
     *
     * @param string $currency Исходная валюта
     *
     * @return string
     */
    private function resolveCurrency(string $currency): string
    {
        return match ($currency) {
            'BTC' => 'btc-btc',
            'ETH' => 'eth-eth',
            'LTC' => 'ltc-ltc',
            'USDTTRC20' => 'usdt-trx',
            'USDCTRC20' => 'usdc-trx',
            'USDTERC20' => 'usdt-eth',
            'USDCERC20' => 'usdc-eth',
            'TRX' => 'trx-trx',
            default => $currency
        };
    }
}
