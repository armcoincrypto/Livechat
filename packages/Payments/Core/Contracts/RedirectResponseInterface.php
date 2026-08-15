<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Contracts;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;

/**
 * Интерфейс для ответов, которые поддерживают редирект пользователя.
 *
 * Реализуется только теми ответами, где действительно есть redirect-сценарий:
 *  - 3-D Secure,
 *  - хостинговая платёжная страница,
 *  - внешние платёжные шлюзы, требующие перехода по URL.
 */
interface RedirectResponseInterface extends ResponseInterface
{
    /**
     * Требует ли ответ редиректа пользователя.
     *
     * Возвращает true, если:
     *  - нужно перенаправить клиента на платёжную страницу;
     *  - требуется авторизация 3-D Secure;
     *  - шлюз использует отдельную страницу/форму для оплаты.
     */
    public function isRedirect(): bool;

    /**
     * URL, на который нужно переадресовать пользователя.
     */
    public function getRedirectUrl(): ?string;

    /**
     * HTTP-метод при редиректе ("GET" или "POST").
     */
    public function getRedirectMethod(): string;

    /**
     * Данные, которые нужно отправить вместе с редиректом (обычно при POST).
     */
    public function getRedirectData(): array;

    /**
     * Быстрый helper: отправить редирект-ответ.
     *
     * В контроллерах Laravel предпочтительнее возвращать getRedirectResponse().
     */
    public function redirect(): void;

    /**
     * Получить HTTP-ответ, реализующий редирект.
     *
     * - GET  → RedirectResponse
     * - POST → HTML-страница с auto-submit формы
     */
    public function getRedirectResponse(): RedirectResponse|HttpResponse;
}
