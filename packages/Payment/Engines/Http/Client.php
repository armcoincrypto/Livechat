<?php

declare(strict_types=1);

namespace iEXPackages\Payment\Engines\Http;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * HTTP-клиент для работы с внешними API.
 *
 * Современный вариант:
 * - использует GuzzleHttp\Client (уже есть в Laravel)
 * - не зависит от HTTPlug, Discovery и фабрик сообщений
 * - возвращает PSR-7 ResponseInterface
 */
class Client implements ClientInterface
{
    /**
     * Экземпляр HTTP-клиента Guzzle.
     */
    private GuzzleClientInterface $httpClient;

    /**
     * @param GuzzleClientInterface|null $httpClient
     *     Можно передать свой Guzzle-клиент (со своими настройками, middleware и т.п.),
     *     либо будет создан дефолтный.
     */
    public function __construct(?GuzzleClientInterface $httpClient = null)
    {
        $this->httpClient = $httpClient ?? new GuzzleClient([
            // Не выбрасывать исключения при 4xx/5xx, отдаём Response наверх.
            'http_errors' => false,
            // Базовый таймаут (можешь потом вынести в конфиг).
            'timeout'     => 30.0,
        ]);
    }

    /**
     * Выполняет HTTP-запрос.
     *
     * @param string                                       $method          HTTP-метод (GET, POST, PUT, DELETE и т.д.)
     * @param string|\Stringable                           $uri             Полный URL или относительный путь
     * @param array<string, string|string[]>               $headers         Заголовки запроса
     * @param string|array|resource|StreamInterface|null   $body            Тело запроса:
     *                                                                      - array  → отправится как JSON
     *                                                                      - string|resource|StreamInterface → сырое тело
     * @param string                                       $protocolVersion Версия HTTP (по умолчанию 1.1)
     *
     * @return ResponseInterface                           PSR-7 ответ
     *
     * @throws GuzzleException                             При сетевых ошибках (проблема соединения и т.п.)
     */
    public function request(
        string $method,
               $uri,
        array $headers = [],
               $body = null,
        string $protocolVersion = '1.1'
    ): ResponseInterface {
        $options = [
            'headers'     => $headers,
            'http_errors' => false,
            'version'     => $protocolVersion,
        ];

        if ($body !== null) {
            if (is_array($body)) {
                // Массив отправляем как JSON — удобно для большинства API.
                $options['json'] = $body;
            } elseif (is_string($body) || is_resource($body) || $body instanceof StreamInterface) {
                $options['body'] = $body;
            } else {
                // Чтобы не получить тихий странный баг с непонятным типом.
                throw new \InvalidArgumentException(
                    sprintf('Unsupported request body type: %s', get_debug_type($body))
                );
            }
        }

        /** @var ResponseInterface $response */
        $response = $this->httpClient->request($method, (string) $uri, $options);

        return $response;
    }
}
