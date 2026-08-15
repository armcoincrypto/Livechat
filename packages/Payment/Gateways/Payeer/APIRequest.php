<?php

namespace iEXPackages\Payment\Gateways\Payeer;

use Exception;
use iEXPackages\Payment\Engines\AbstractAPIRequest;
use Illuminate\Support\Facades\Http;

class APIRequest extends AbstractAPIRequest
{
    /**
     * URL Payeer API
     *
     * @var string
     */
    protected string $baseUrl = 'https://payeer.com/ajax/api/api.php';

    /**
     * Создаем конструктор Payeer
     */
    public function __construct(array $parameters = [])
    {
        $this->initialize($parameters);
    }

    /**
     * Перевод средств
     *
     * @throws Exception
     */
    public function transfer(array $options = []): array
    {
        $this->typeLog = 'pay';
        $array = [
            'action' => 'transfer',
            'sumOut' => iex_number_format($options['amount'], 2),
            'curIn' => mb_strtoupper($options['currency']),
            'curOut' => mb_strtoupper($options['currency']),
            'to' => $options['to'],
        ];

        // Если комиссию платит клиент
        if (isset($options['who_commission']) and $options['who_commission'] == 1) {
            unset($array['sumOut']);
            $array['sum'] = iex_number_format($options['amount'], 2);
        }

        if (isset($options['comment'])) {
            $array['comment'] = $options['comment'];
        }

        return (array) $this->call($array);
    }

    /**
     * Выплата на счет клиента
     *
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function sendToWithdrawal(array $options = []): array
    {
        $this->typeLog = 'pay';
        $response = $this->transfer([
            'amount' => $options['amount'],
            'currency' => $options['code'],
            'to' => $options['score'],
            'comment' => $options['comment']
        ]);

        if(!$response['success']) {
            throw new \Exception(json_encode($response));
        }

        return [
            'status' => 0,
            'response' => $response
        ];
    }

    /**
     * Вызов определенных действий для "PayeerAPI"
     *
     * @param $action
     *
     * @return array|mixed
     * @throws Exception
     */
    protected function call($action): mixed
    {
        // Если полученное значение не является массивом
        if (! is_array($action)) {
            $action = ['action' => $action];
        }

        $data = array_merge([
            'account' => $this->getParameter('account_id'),
            'apiId' => $this->getParameter('api_id'),
            'apiPass' => $this->getParameter('api_key'),
        ], $action);


        // Отправляем язык
        $data['language'] = app()->getLocale();
        $response = Http::asForm()->post($this->baseUrl, $data)->json();

        if ($response['auth_error'] == 1) {
            throw new Exception(array_first($response['errors']));
        }

        return $response;
    }
}
