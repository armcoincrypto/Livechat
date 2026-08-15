<?php

namespace iEXPackages\AMLPlugin\Drivers\AmlBot;

use App\Models\AMLService;
use DateTimeImmutable;
use iEXPackages\AMLPlugin\Contracts\AMLDriverInterface;
use iEXPackages\AMLPlugin\Contracts\AMLResponseInterface;
use iEXPackages\AMLPlugin\Exceptions\AMLDriverException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Random\RandomException;


/**
 * Класс AmlBotDriver
 *
 * Драйвер для интеграции с AML-сервисом AmlBot. Предоставляет функционал
 * проверки транзакций и адресов через внешний API сервиса.
 *
 * @package iEXPackages\AMLPlugin\Drivers\AmlBot
 */
class AmlBotDriver implements AMLDriverInterface
{
    /**
     * Общая конфигурация драйвера.
     *
     * @var array
     */
    protected array $config;

    /**
     * Защищённая конфигурация драйвера (ключи доступа, секреты).
     *
     * @var array
     */
    protected array $protectedConfig;

    /**
     * Модель сервиса AML (опционально).
     *
     * @var AMLService|null
     */
    protected ?AMLService $service;

    /**
     * Конструктор драйвера.
     *
     * @param array           $config          Общая конфигурация
     * @param array           $protectedConfig Защищённая конфигурация
     * @param AMLService|null $service         Модель AML-сервиса
     */
    public function __construct(array $config, array $protectedConfig = [], AMLService $service = null)
    {
        $this->config = $config;
        $this->protectedConfig = $protectedConfig;
        $this->service = $service;
    }

    /**
     * Выполняет проверку транзакции через AmlBot API.
     *
     * @param array $params ['tx', 'address', 'currency']
     *
     * @return AMLResponseInterface
     *
     * @throws AMLDriverException
     * @throws ConnectionException
     */
    public function checkTransaction(array $params): AMLResponseInterface
    {
        $token = $this->generateToken($params['tx']);

        $requestParams = [
            'accessId'  => $this->protectedConfig['access_id'],
            'locale'    => 'en_US',
            'hash'      => $params['tx'],
            'address'   => $params['address'],
            'direction' => 'withdrawal',
            'asset'     => $this->resolveCurrency($params['currency']),
            'token'     => $token,
        ];

        $resultResponse = $this->callRequest($requestParams);

        return new AmlBotResponse($resultResponse, $this->protectedConfig);
    }

    /**
     * Выполняет проверку адреса через AmlBot API.
     *
     * @param array $params ['address', 'currency']
     *
     * @return AMLResponseInterface
     *
     * @throws AMLDriverException
     * @throws ConnectionException
     */
    public function checkAddress(array $params): AMLResponseInterface
    {
        $token = $this->generateToken($params['address']);

        $requestParams = [
            'accessId' => $this->protectedConfig['access_id'],
            'locale'   => 'en_US',
            'hash'     => $params['address'],
            'asset'    => $this->resolveCurrency($params['currency']),
            'token'    => $token,
        ];

        $resultResponse = $this->callRequest($requestParams);

        return new AmlBotResponse($resultResponse, $this->protectedConfig);
    }

    /**
     * Генерирует уникальный токен авторизации для запроса.
     *
     * @param string $value Хеш транзакции или адрес
     *
     * @return string MD5-хеш токена
     */
    private function generateToken(string $value): string
    {
        return hash('md5', implode(':', [
            $value,
            $this->protectedConfig['access_key'],
            $this->protectedConfig['access_id'],
        ]));
    }

    /**
     * Выполняет HTTP-запрос к AmlBot API.
     *
     * @param array $options Параметры для отправки
     *
     * @return array Ответ API в виде массива
     *
     * @throws AMLDriverException|ConnectionException При ошибках HTTP-запроса или ошибках API
     */
    private function callRequest(array $options): array
    {
        try {
            $response = Http::asForm()->post('https://extrnlapiendpoint.silencatech.com/', $options);
            $data = $response->json();

            if ($response->failed() || empty($data['result'])) {
                throw new AMLDriverException('Ошибка AML-проверки: ' . json_encode($data, JSON_UNESCAPED_UNICODE));
            }

            return $data;
        } catch (RequestException $e) {
            throw new AMLDriverException('Ошибка подключения к AmlBot API: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Приводит валюту к формату, совместимому с AML-сервисом AmlBot.
     *
     * @param string $currency Исходная валюта (например, USDTTRC20)
     *
     * @return string Приведённая к совместимому формату валюта.
     */
    private function resolveCurrency(string $currency): string
    {
        return match (strtoupper($currency)) {
            'USDTTRC20' => 'TRX',
            'USDTERC20' => 'ETH',
            default => strtoupper($currency)
        };
    }
}
