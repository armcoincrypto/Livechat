<?php

namespace iEXPackages\Payment\Engines\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * Интерфейс HTTP‑клиента для работы с внешними API.
 *
 * Реализации этого интерфейса должны:
 * - выполнять HTTP‑запросы к удалённым сервисам;
 * - возвращать PSR‑7 ResponseInterface;
 * - не выбрасывать исключения при HTTP‑ошибках (4xx/5xx), а только при сетевых/транспортных ошибках
 *   (конкретные типы исключений зависят от реализации, например GuzzleException).
 */
interface ClientInterface
{
    /**
     * Выполняет HTTP‑запрос.
     *
     * @param  string                         $method          HTTP‑метод (GET, POST, PUT, DELETE и т.д.)
     * @param  string|UriInterface            $uri             Полный URL или относительный путь
     * @param  array<string,string|string[]>  $headers         Заголовки запроса
     * @param  resource|string|StreamInterface|null $body      Тело запроса:
     *                                                         - array обычно должен обрабатываться реализацией
     *                                                           (например, как JSON);
     *                                                         - string|resource|StreamInterface — сырое тело запроса.
     * @param  string                         $protocolVersion Версия HTTP (по умолчанию 1.1)
     *
     * @return ResponseInterface                               PSR‑7 ответ
     */
    public function request(
        string $method,
        $uri,
        array $headers = [],
        $body = null,
        string $protocolVersion = '1.1'
    ): ResponseInterface;
}
