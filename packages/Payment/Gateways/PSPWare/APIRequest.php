<?php

namespace iEXPackages\Payment\Gateways\PSPWare;

use App\Models\Task;
use Exception;
use iEXPackages\Payment\Engines\AbstractAPIRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class APIRequest extends AbstractAPIRequest
{
    /**
     * Конструктор
     */
    public function __construct(array $config = [])
    {
        $this->initialize($config);
        $this->baseUrl = 'https://api.pspware.space';
    }

    private function formatSumForSignature(float $sum): string
    {
        // Используем bcmod для избежания ошибок сравнения float
        if (bcmod(sprintf('%.10f', $sum), '1', 10) == 0) {
            // Если число целое, оставляем один знак после точки
            return number_format($sum, 1, '.', '');
        }

        // В остальных случаях — два знака после запятой
        return number_format($sum, 2, '.', '');
    }

    /**
     * Баланс кошелька
     *
     * @param string $order_id
     * @param string $amount
     * @return array
     * @throws Exception
     */
    public function findTransaction(string $order_id, string $amount): array
    {
        $formattedSum = $this->formatSumForSignature((float) $amount);
        $stringToSign = "{$formattedSum}:{$this->getParameter('api_key')}";
        $signature = hash('sha256', mb_convert_encoding($stringToSign, 'UTF-8'));

        $result = $this->call('/payphoria/merchant/api/v1/orders/' . $order_id, 'POST', [
            'sign' => $signature,
        ]);

        if (!isset($result['id'])) {
            return [];
        }

        return $result ?: [];
    }

    /**
     * Проверка поступлений
     * @throws Exception
     */
    public function checkPayment($options = []): CheckPayment
    {
        // Получаем детали транзакции по адресу с сервера
        $tx_refs = $this->findTransaction($options['id_from_merchant'], $options['income']['amount']);

        if (!is_array($tx_refs)) {
            throw new \Exception(json_encode($tx_refs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        return new CheckPayment($tx_refs);
    }

    public function sendToWithdrawal(array $options = []): array
    {
        $this->typeLog = 'pay';
        $merchantData = $this->getPayGateway();
        $typePay = ($merchantData->ext_options['method_pay'] ?? 'card');
        $paymentMethod = !(($typePay === 'card'));


        $amount = $options['amount'];
        $score = $options['score'];

        $params = [
            'amount' => $amount,
            'score' => $score,
            'payment_method' => $paymentMethod,
        ];

        if (isset($options['buy_additional_fields']['recipient_fullname'])) {
            $params['cardHolder'] = $options['buy_additional_fields']['recipient_fullname'];
        }


        // Тип транзакции
        $response = $this->transfer($params);

        return [
            'status' => 0,
            'withdrawal_id' => $response['id'],
            'is_callback' => true,
            'defer_success' => true,
        ];
    }

    /**
     * Отправить на счет
     *
     * @throws Exception
     */
    public function transfer($params = [])
    {
        $this->typeLog = 'pay';

        $formattedSum = $this->formatSumForSignature((float) $params['amount']);
        $stringToSign = "{$formattedSum}:{$this->getParameter('api_key')}";
        $signature = hash('sha256', mb_convert_encoding($stringToSign, 'UTF-8'));

        $options = [
            'sum' => $formattedSum,
            'currency' => 'RUB',
            'orderType' => 'PAY-OUT',
            'bank' => 'any-bank',
            'merchant_id' => $this->parameters->get('merchant_id'),
            'signature' => $signature,
            'isSbp' => $params['payment_method'],
            'recipient' => $params['cardHolder'] ?? '',
            'requisite' => $params['score'] ?? '',
        ];

        return $this->call('/payphoria/merchant/api/v1/orders', 'POST', $options);
    }

    /**
     * Вызов для получения данных
     *
     * @return array
     *
     * @throws Exception
     */
    private function call(string $path, string $method, array $options = [])
    {
        $method = Str::lower($method);

        $response = Http::baseUrl($this->baseUrl)
            ->asJson()
            ->{$method}($path, $options);

        // Логирование запроса и ответа
        Log::info('APIRequest call', [
            'method' => strtoupper($method),
            'url' => $this->baseUrl . $path,
            'request' => $options,
            'response_status' => $response->status(),
            'response_body' => $response->body()
        ]);

        return $response->json();
    }
}
