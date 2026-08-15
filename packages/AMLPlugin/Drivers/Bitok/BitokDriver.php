<?php

namespace iEXPackages\AMLPlugin\Drivers\Bitok;

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
 * Драйвер для взаимодействия с AML-сервисом Bitok.
 *
 * Предоставляет методы для проверки транзакций и адресов через API KYT Bitok.
 *
 * @package iEXPackages\AMLPlugin\Drivers\Bitok
 */
class BitokDriver implements AMLDriverInterface
{
    /**
     * Общая конфигурация драйвера.
     *
     * @var array
     */
    protected array $config;

    /**
     * Защищённая конфигурация (ключи, секреты и пр.).
     *
     * @var array
     */
    protected array $protectedConfig;

    /**
     * Модель AML-сервиса (опционально).
     *
     * @var AMLService|null
     */
    protected ?AMLService $service;

    /**
     * Конструктор драйвера.
     *
     * @param array           $config          Общая конфигурация
     * @param array           $protectedConfig Защищённая конфигурация
     * @param AMLService|null $service         Модель сервиса AML
     */
    public function __construct(array $config, array $protectedConfig = [], AMLService $service = null)
    {
        $this->config = $config;
        $this->protectedConfig = $protectedConfig;
        $this->service = $service;
    }

    /**
     * Выполняет AML-проверку транзакции.
     *
     * @param array $params ['currency', 'tx', 'address']
     *
     * @return AMLResponseInterface
     *
     * @throws \Exception
     */
    public function checkTransaction(array $params): AMLResponseInterface
    {
        $payload = [
            'client_id'      => null,
            'direction'      => 'incoming',
            'network'        => $params['currency'] ?? '',
            'tx_hash'        => $params['tx'] ?? '',
            'output_address' => $params['address'] ?? '',
            'token_id'       => null,
        ];

        $response = $this->callRequest('post', '/v1/transfers/register/', $payload);
        $transfer = $this->callRequest('get', '/v1/transfers/' . (string)$response['id'] . '/');

        return new BitokResponse($transfer, $this->protectedConfig);
    }

    /**
     * Выполняет AML-проверку адреса.
     *
     * @param array $params ['currency', 'address', 'amount']
     *
     * @return AMLResponseInterface
     *
     * @throws \Exception
     */
    public function checkAddress(array $params): AMLResponseInterface
    {
        $currency = $params['currency'] ?? '';
        $network = $currency;
        $token_id = null;

        if ($currency === 'USDTTRC20') {
            $network = 'TRX';
            $token_id = 'USDT';
        } elseif ($currency === 'USDTERC20') {
            $network = 'ETH';
            $token_id = 'USDT';
        }

        $payload = [
            'client_id'      => null,
            'attempt_id'     => time(),
            'direction'      => 'outgoing',
            'network'        => $network,
            'token_id'       => $token_id,
            'amount'         => (float)($params['amount'] ?? 0),
            'output_address' => $params['address'] ?? '',
        ];

        $response = $this->callRequest('post', '/v1/transfers/register-attempt/', $payload);
        $transfer = $this->callRequest('get', '/v1/transfers/' . (string)$response['id'] . '/');

        return new BitokResponse($transfer, $this->protectedConfig);
    }

    /**
     * Выполняет HTTP-запрос к API KYT Bitok с авторизацией.
     *
     * @param string $method HTTP-метод запроса (get/post)
     * @param string $path Путь к API-ресурсу
     * @param array  $json_payload Параметры запроса
     *
     * @return array Ответ API в виде массива
     *
     * @throws \Exception Если запрос завершился с ошибкой
     */
    private function callRequest(string $method, string $path, array $json_payload = []): array
    {
        $apiUrl = 'https://kyt-api.bitok.org';
        $timestamp = round(microtime(true) * 1000);
        $method = strtoupper($method);

        $signature = $this->generateSignature($method, $path, $timestamp, $json_payload);

        $headers = [
            'API-KEY-ID'    => $this->protectedConfig['api_key'],
            'API-TIMESTAMP' => $timestamp,
            'API-SIGNATURE' => $signature,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];

        $response = Http::baseUrl($apiUrl)
            ->withHeaders($headers)
            ->{$method}($path, $json_payload);

        if ($response->failed()) {
            throw new \Exception('Ошибка API KYT Bitok: ' . $response->body());
        }

        $responseData = $response->json();

        if (empty($responseData)) {
            throw new \Exception('Получен пустой ответ от API KYT Bitok.');
        }

        return $responseData;
    }

    /**
     * Генерирует подпись для авторизации запросов к API.
     *
     * @param string $method    HTTP-метод запроса
     * @param string $path      Эндпоинт API
     * @param int    $timestamp Метка времени (в миллисекундах)
     * @param array  $payload   Тело запроса (опционально)
     *
     * @return string Подпись в формате Base64
     */
    private function generateSignature(string $method, string $path, int $timestamp, array $payload = []): string
    {
        $parts = [
            strtoupper($method),
            $path,
            (string)$timestamp,
        ];

        if (!empty($payload)) {
            $parts[] = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $stringToSign = implode("\n", $parts);

        return base64_encode(
            hash_hmac('sha256', $stringToSign, $this->protectedConfig['secret_api'], true)
        );
    }
}
