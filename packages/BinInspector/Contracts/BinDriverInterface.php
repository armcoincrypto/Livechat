<?php

declare(strict_types=1);

namespace iEXPackages\BinInspector\Contracts;

use iEXPackages\BinInspector\BinInspectorResult;

/**
 * Контракт драйвера для получения информации по BIN.
 *
 * Каждый драйвер отвечает за работу с конкретным внешним сервисом (Binlist, BinCodes, MrBin и т.д.).
 */
interface BinDriverInterface
{
    /**
     * Установить номер карты (PAN).
     *
     * Допускается произвольный формат (с пробелами, дефисами и т.п.),
     * внутри драйвер сам оставит только цифры.
     *
     * @param string $cardNumber Номер карты в любом виде
     * @return static
     */
    public function setCardNumber(string $cardNumber): static;

    /**
     * Установить API-ключ для провайдера.
     *
     * Если ключ не задан (null или пустая строка),
     * драйвер просто не будет выполнять запросы к API, где ключ обязателен.
     *
     * @param string|null $apiKey
     * @return static
     */
    public function setApiKey(?string $apiKey): static;

    /**
     * Включить или отключить сохранение данных в кэш.
     *
     * true / 1  – кэш включён
     * false / 0 – кэш отключён
     *
     * @param int|bool $isSaveData
     * @return static
     */
    public function setIsSaveData(int|bool $isSaveData): static;

    /**
     * Выполнить запрос к провайдеру и получить нормализованный результат.
     *
     * @return BinInspectorResult|null
     */
    public function fetch(): ?BinInspectorResult;

    /**
     * Получить платёжную систему (Visa, MasterCard и т.п.).
     *
     * @return string|null
     */
    public function getPaymentSystem(): ?string;

    /**
     * Получить тип карты (debit, credit и т.п.).
     *
     * @return string|null
     */
    public function getType(): ?string;

    /**
     * Проверить валидность карты/данных (общее поле isValid).
     *
     * @return bool
     */
    public function isValid(): bool;

    /**
     * Старое имя метода валидации — для совместимости.
     *
     * @return bool
     */
    public function getValidate(): bool;

    /**
     * Получить бренд карты (Gold, Platinum и т.п.).
     *
     * @return string|null
     */
    public function getBrand(): ?string;

    /**
     * Получить название страны банка-эмитента.
     *
     * @return string|null
     */
    public function getCountryName(): ?string;

    /**
     * Получить валюту (ISO-код).
     *
     * @return string|null
     */
    public function getCurrency(): ?string;

    /**
     * Старое алиас-имя метода — для совместимости.
     *
     * @return string|null
     */
    public function geCurrency(): ?string;

    /**
     * Получить название банка.
     *
     * @return string|null
     */
    public function getBankName(): ?string;

    /**
     * Получить URL сайта банка.
     *
     * @return string|null
     */
    public function getBankUrl(): ?string;

    /**
     * Получить телефон службы поддержки банка.
     *
     * @return string|null
     */
    public function getBankSupportNumber(): ?string;

    /**
     * Получить сырые данные, возвращённые провайдером.
     *
     * @return array<mixed>
     */
    public function getRawData(): array;

    /**
     * Получить данные в унифицированном формате для проекта.
     *
     * Структура:
     * [
     *   'phone'         => string|null,
     *   'website'       => string|null,
     *   'bankName'      => string|null,
     *   'countryName'   => string|null,
     *   'currencyName'  => string|null,
     *   'brandName'     => string|null,
     *   'typeName'      => string|null,
     *   'paymentSystem' => string|null,
     * ]
     *
     * @return array<string, string|null>
     */
    public function getData(): array;
}
