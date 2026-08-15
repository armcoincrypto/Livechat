<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Contracts;

use App\Models\Task;

/**
 * Интерфейс базового Request к платёжному шлюзу.
 *
 * Request представляет собой "операцию":
 *  - хранит входные параметры (payload),
 *  - умеет собирать данные для отправки (getData()),
 *  - умеет отправлять запрос и возвращать Response (send()).
 *
 * Примеры конкретных реализаций:
 *  - PurchaseRequest
 *  - CompletePurchaseRequest (callback/IPN)
 *  - PayoutRequest
 *  - CheckPaymentRequest
 */
interface RequestInterface
{
    /**
     * Инициализация входных параметров Request.
     *
     * Обычно вызывается из фабрики/gateway:
     *  $request->initialize(['amount' => '100', 'currency' => 'USDT']);
     */
    public function initialize(array $parameters = []): static;

    /**
     * Получить все текущие параметры Request (payload) в виде массива.
     */
    public function getParameters(): array;

    /**
     * Получить значение конкретного параметра по ключу.
     *
     * @param string $key     Имя параметра
     * @param mixed  $default Значение по умолчанию, если параметр отсутствует
     */
    public function getParameter(string $key, mixed $default = null): mixed;

    /**
     * Установить значение конкретного параметра.
     *
     * Должен возвращать $this для поддержки fluent-цепочек:
     *  $request->setParameter('amount', '100')->setParameter('currency', 'USDT');
     */
    public function setParameter(string $key, mixed $value): static;

    /**
     * Массовое добавление/обновление параметров.
     *
     * Пример:
     *  $request->addParameters(['amount' => '100', 'currency' => 'USDT']);
     */
    public function addParameters(array $parameters): static;

    /**
     * Собрать payload для HTTP/другого запроса.
     *
     * Это "готовые" данные, которые пойдут во внешний запрос к API шлюза:
     *  - с учётом валидации;
     *  - с учётом нормализации;
     *  - с учётом конфига мерчанта (merchant config).
     */
    public function getData(): array;


    public function withTask(Task $task): static;

    public function setTransactionId(string $id): static;

    /**
     * Отправить запрос к платёжному шлюзу и получить Response.
     *
     * Внутри:
     *  - формирует payload через getData();
     *  - выполняет внешний запрос (HTTP/API);
     *  - создаёт объект, реализующий ResponseInterface;
     *  - возвращает этот Response.
     *
     * @return ResponseInterface
     */
    public function send(): ResponseInterface;
}
