<?php

namespace iEXPackages\Payment\Gateways\WhiteBitCrypto\Message;

use iEXPackages\Payment\Engines\Message\AbstractRequest as BaseAbstractRequest;

abstract class AbstractRequest extends BaseAbstractRequest
{
    /**
     * Конченый URL для запросов
     *
     * @return string
     */
    protected string $endpointUrl = 'https://whitebit.com';

    /**
     * Получить Публичный ключ
     */
    public function getPublicKey(): string
    {
        return $this->getParameter('public_key');
    }

    /**
     * Получить Секретный ключ
     */
    public function getSecretKey(): string
    {
        return $this->getParameter('secret_key');
    }
}
