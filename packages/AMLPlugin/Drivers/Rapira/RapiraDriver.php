<?php

namespace iEXPackages\AMLPlugin\Drivers\Rapira;

use App\Models\AMLService;
use DateTimeImmutable;
use iEXPackages\AMLPlugin\Contracts\AMLDriverInterface;
use iEXPackages\AMLPlugin\Contracts\AMLResponseInterface;
use iEXPackages\AMLPlugin\Exceptions\AMLDriverException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Random\RandomException;

/**
 * Класс RapiraDriver
 *
 * Реализует взаимодействие с AML-сервисом Rapira, предоставляя методы
 * для проверки транзакций и адресов с использованием JWT-авторизации.
 *
 * @package iEXPackages\AMLPlugin\Drivers\Rapira
 */
class RapiraDriver implements AMLDriverInterface
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
     * Конструктор драйвера RapiraDriver.
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
     * Выполняет проверку транзакции через Rapira API.
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
        $resultResponse = $this->callRequest('check-transaction', [
            'hash'      => $params['tx'] ?? '',
            'address'   => $params['address'] ?? '',
            'token'     => $this->resolveCurrency($params['currency'] ?? ''),
            'direction' => 'WITHDRAWAL',
        ]);

        return new RapiraResponse($resultResponse, $this->protectedConfig);
    }

    /**
     * Выполняет проверку адреса через Rapira API.
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
        $resultResponse = $this->callRequest('check-address', [
            'address' => $params['address'] ?? '',
            'chain'   => $this->resolveCurrency($params['currency'] ?? ''),
        ]);

        return new RapiraResponse($resultResponse, $this->protectedConfig);
    }

    /**
     * Отправляет защищённый запрос к Rapira AML API.
     *
     * @param string $path Путь API ('check-transaction' или 'check-address')
     * @param array $options Параметры запроса
     *
     * @return array Ответ API
     *
     * @throws AMLDriverException Если произошла ошибка запроса или ответа
     * @throws ConnectionException Если произошла ошибка запроса или ответа
     * @throws RandomException
     * @throws \DateMalformedStringException
     */
    private function callRequest(string $path, array $options): array
    {
        $apiHost = 'https://' . trim($this->protectedConfig['api_host'], '/');

        $authToken = $this->getAuthToken($apiHost);

        $response = Http::baseUrl($apiHost)
            ->withToken($authToken)
            ->asForm()
            ->post('/open/aml/' . $path, $options);

        $data = $response->json();

        if ($response->failed() || empty($data['uuid'])) {
            throw new AMLDriverException('Ошибка проверки AML (' . $path . '): ' . json_encode($data, JSON_UNESCAPED_UNICODE));
        }

        return $data;
    }


    /**
     * Генерирует JWT-токен для авторизации запросов к Rapira API.
     *
     * @return string JWT-токен
     *
     * @throws AMLDriverException Если приватный ключ некорректен
     * @throws \DateMalformedStringException
     * @throws RandomException
     */
    private function generateJwtToken(): string
    {
        $privateKeyDecoded = base64_decode($this->protectedConfig['private_key'], true);

        if (!$privateKeyDecoded) {
            throw new AMLDriverException('Некорректный приватный ключ для JWT.');
        }

        $key = InMemory::plainText($privateKeyDecoded);
        $config = Configuration::forSymmetricSigner(new Sha256(), $key);

        $now = new DateTimeImmutable();

        return $config->builder()
            ->identifiedBy(bin2hex(random_bytes(12)))
            ->expiresAt($now->modify('+1 month'))
            ->getToken($config->signer(), $config->signingKey())
            ->toString();
    }

    /**
     * Получает авторизационный токен API через JWT.
     *
     * @param string $apiHost Хост API
     *
     * @return string
     *
     * @throws AMLDriverException Если не удалось получить токен
     * @throws RandomException
     * @throws \DateMalformedStringException
     * @throws ConnectionException
     */
    private function getAuthToken(string $apiHost): string
    {
        $jwtToken = $this->generateJwtToken();

        $authResponse = Http::baseUrl($apiHost)
            ->acceptJson()
            ->post('/open/generate_jwt', [
                'kid' => $this->protectedConfig['uuid'],
                'jwt_token' => $jwtToken,
            ]);

        if ($authResponse->failed() || empty($authResponse->json('token'))) {
            throw new AMLDriverException('Ошибка получения JWT-токена авторизации: ' . $authResponse->body());
        }

        return $authResponse->json('token');
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
            'USDTTRC20' => 'TRX',
            'USDTERC20' => 'ETH',
            default => $currency
        };
    }
}
