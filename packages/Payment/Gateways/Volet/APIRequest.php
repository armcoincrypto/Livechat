<?php

namespace iEXPackages\Payment\Gateways\Volet;

use Exception;
use iEXPackages\Payment\Engines\AbstractAPIRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Str;
use SoapFault;

class APIRequest extends AbstractAPIRequest
{
    protected string $baseUrl = 'https://account.volet.com/wsm/merchantWebService?wsdl';

    public array $soapOptions = [
        'location' => 'https://account.volet.com/wsm/merchantWebService',
    ];

    /**
     * "SoapClient"
     */
    protected \SoapClient $soapClient;

    /**
     * Создаем конструктор Volet
     *
     * @throws SoapFault|Exception
     */
    public function __construct(array $parameters = [])
    {
        $this->initialize($parameters);

        try {
            $this->soapClient = new \SoapClient($this->baseUrl, $this->soapOptions);
        } catch (SoapFault $e) {
            throw new \Exception($e);
        }
    }

    /**
     * Внутрисистемный платеж.
     *
     * @param $amount
     * @param $currency
     * @param $email
     * @param $walletId
     * @param null $note
     * @param bool|false $savePaymentTemplate
     * @return mixed
     *
     * @throws SoapFault
     */
    public function sendMoney($amount, $currency, $email, $walletId, $note = null, bool $savePaymentTemplate = false): mixed
    {
        // Заменить RUB на RUR
        $currency = str_replace('RUB', 'RUR', $currency);

        return $this->call('sendMoney', [
            'amount' => iex_number_format($amount, 2),
            'currency' => $currency,
            'email' => $email,
            'walletId' => $walletId,
            'note' => $note,
            'savePaymentTemplate' => $savePaymentTemplate,
        ]);
    }

    /**
     * Вывод средств незарегистрированному пользователю по e-mail.
     *
     * @param $amount
     * @param $currency
     * @param $email
     * @param null $note
     * @return mixed
     *
     * @throws SoapFault
     */
    public function sendMoneyToEmail($amount, $currency, $email, $note = null): mixed
    {
        // Заменить RUB на RUR
        $currency = str_replace('RUB', 'RUR', $currency);

        return $this->call('sendMoneyToEmail', [
            'amount' => iex_number_format($amount, 2),
            'currency' => $currency,
            'email' => $email,
            'note' => $note,
        ]);
    }

    /**
     * Выплата на счет клиента
     *
     * @param array $options
     * @return array
     * @throws ConnectionException
     */
    public function sendToWithdrawal(array $options = []): array
    {
        $apiService = $this->getPayGateway();

        $type_transaction = ($apiService['ext_options']['type_transaction'] ?? 'wallet');

        if($type_transaction == 'wallet') {
            $sendRequest = $this->sendMoney(
                (float)$options['amount'],
                (string)$options['code'],
                '',
                Str::upper($options['score']),
                (string)$options['comment']
            );
        } else {
            $sendRequest = $this->sendMoneyToEmail(
                (float)$options['amount'],
                (string)$options['code'],
                Str::upper($options['score']),
                (string)$options['comment']
            );
        }

        return [
            'status' => 0,
            'response' => $sendRequest
        ];
    }


    /**
     * Получение баланса по кошелькам пользователя.
     *
     * @param string $currency
     * @return array| float
     *
     * @throws SoapFault
     */
    public function getBalance(string $currency)
    {
        // Список доступных кодов валют, платежной системы
        $codes = [
            'U' => 'USD',  'E' => 'EUR', 'R' => 'RUB', 'G' => 'GBP', 'H' => 'UAH',
        ];

        $return = [];
        foreach ($this->call('getBalances') as $item) {
            $split = mb_substr($item['id'], 0, 1);

            // Если нет кошелька
            if (! isset($codes[$split])) {
                continue;
            }
            $return[$codes[$split]] = $item['amount'];
        }

        return $return[$currency];
    }

    /**
     * Вызов определенных действий для "SoapClient"
     *
     * @param  null  $params
     * @return mixed
     *
     * @throws SoapFault
     * @throws Exception
     */
    protected function call($method, $params = null)
    {

        if($this->parameters->has('sci_account_email')) {
            $email = $this->parameters->get('sci_account_email');
        } else {
            $email = $this->parameters->get('api_account_email');
        }

        try {
            $result = $this->soapClient->{$method}([
                'arg0' => [
                    'apiName' => $this->parameters->get('api_name'),
                    'authenticationToken' => $this->createAuthToken(),
                    'accountEmail' => $email,
                ],
                'arg1' => $params,
            ]);
        } catch (SoapFault $e) {
            throw new \Exception($e->getMessage());
        }

        return $this->processResult($result);
    }

    /**
     * Преобразование stdObject в массив
     *
     * @return mixed
     */
    protected function processResult($result)
    {
        return json_decode(
            json_encode(
                $this->getValue($result, 'return', [])
            ), true
        );
    }

    /**
     * Создаем токен для авторизации на сервисе AdvCash
     *
     * @throws Exception
     */
    protected function createAuthToken(): string
    {
        $date = new \DateTime('now', new \DateTimeZone('UTC'));

        return strtoupper(hash('sha256', implode(':', [
            $this->getParameter('api_secret'),
            $date->format('Ymd'),
            $date->format('H'),
        ])));
    }

    protected function getValue($array, $key, $default = null)
    {
        if ($key instanceof \Closure) {
            return $key($array, $default);
        }
        if (is_array($key)) {
            $lastKey = array_pop($key);
            foreach ($key as $keyPart) {
                $array = static::getValue($array, $keyPart);
            }
            $key = $lastKey;
        }
        if (is_array($array) && (isset($array[$key]) || array_key_exists($key, $array))) {
            return $array[$key];
        }
        if (($pos = strrpos($key, '.')) !== false) {
            $array = static::getValue($array, substr($key, 0, $pos), $default);
            $key = substr($key, $pos + 1);
        }
        if (is_object($array)) {
            return $array->$key;
        } elseif (is_array($array)) {
            return (isset($array[$key]) || array_key_exists($key, $array)) ? $array[$key] : $default;
        }

        return $default;
    }
}
