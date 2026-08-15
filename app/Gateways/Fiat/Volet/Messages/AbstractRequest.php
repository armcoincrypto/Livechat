<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Volet\Messages;

use DateTime;
use DateTimeZone;
use iEXPackages\Payments\Core\Engine\AbstractRequest as BaseAbstractRequest;
use InvalidArgumentException;

/**
 * Базовый Request для шлюза Volet.
 *
 * Здесь:
 * - подключаются Generated*Inputs (позже генератором)
 * - задаётся базовый endpoint (можно статически или из конфига)
 * - создаётся HttpClient и единый callApi() для всех операций
 */
abstract class AbstractRequest extends BaseAbstractRequest
{
    // Пример подключения (когда сгенерируешь):
    use Traits\GeneratedMerchantInputs,
        Traits\GeneratedPayInputs;


    protected function endpointStaticBaseUrl(): string
    {
        return 'https://account.volet.com/wsm/merchantWebService?wsdl';
    }

    protected function soapConfig(): array
    {
        return [
            'wsdl' => 'https://account.volet.com/wsm/merchantWebService?wsdl',
            'options' => [
                'trace'      => true,
                'exceptions' => true,
                // при необходимости:
                // 'cache_wsdl' => WSDL_CACHE_NONE,
                // 'connection_timeout' => 10,
            ],
        ];
    }

    /**
     * Единая точка SOAP-вызова для Volet.
     *
     * Точно повторяет старую схему:
     *  - method($payload) где payload = ['arg0'=>auth, 'arg1'=>params]
     *  - возвращает нормализованный массив результата
     *
     * @param string $soapMethod Например: sendMoney, sendMoneyToEmail, getBalances
     * @param array $params Параметры метода (будут в arg1)
     * @return array<string,mixed>
     */
    final protected function soapCall(string $soapMethod, array $params = []): array
    {
        $soapMethod = trim($soapMethod);
        if ($soapMethod === '') {
            throw new InvalidArgumentException('soapCall: method is empty');
        }

        $payload = [
            'arg0' => $this->buildAuthBlock(),
            'arg1' => $params,
        ];

        return $this->sendSoapRequest($soapMethod, $payload);
    }

    /**
     * arg0 как в старом коде:
     * apiName + authenticationToken + accountEmail
     *
     * @return array{apiName:string, authenticationToken:string, accountEmail:string}
     */
    final protected function buildAuthBlock(): array
    {
        $apiName = $this->getConnectionString('getApiName', '');
        if ($apiName === '') {
            // merchant: api_name, pay: api_name тоже должен быть
            // (у тебя оно hidden в конфиге)
            throw new InvalidArgumentException('Volet: api_name is empty');
        }

        $email = $this->getPayApiAccountEmail();
        if ($email === '') {
            throw new InvalidArgumentException('Volet: account email is empty');
        }

        return [
            'apiName'              => $apiName,
            'authenticationToken'  => $this->createAuthToken(),
            'accountEmail'         => $email,
        ];
    }

    /**
     * Токен авторизации как у тебя в старом:
     * strtoupper(sha256(api_secret : Ymd : H)) (UTC)
     */
    final protected function createAuthToken(): string
    {
        $secret = $this->getConnectionString('getApiSecret', '');

        if ($secret === '') {
            throw new InvalidArgumentException('Volet: api_secret is empty');
        }

        $dt = new DateTime('now', new DateTimeZone('UTC'));

        return strtoupper(hash('sha256', implode(':', [
            $secret,
            $dt->format('Ymd'),
            $dt->format('H'),
        ])));
    }
}
