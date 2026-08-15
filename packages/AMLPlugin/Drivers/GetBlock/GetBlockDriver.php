<?php
namespace iEXPackages\AMLPlugin\Drivers\GetBlock;

use App\Models\AMLService;
use iEXPackages\AMLPlugin\Contracts\AMLDriverInterface;
use iEXPackages\AMLPlugin\Contracts\AMLResponseInterface;
use iEXPackages\AMLPlugin\Exceptions\AMLDriverException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Класс GetBlockDriver
 *
 * Реализует взаимодействие с AML-сервисом GetBlock для проверки транзакций и адресов.
 *
 * @package iEXPackages\AMLPlugin\Drivers\GetBlock
 */
class GetBlockDriver implements AMLDriverInterface
{
    /**
     * Общая конфигурация драйвера
     *
     * @var array
     */
    protected array $config;

    /**
     * Защищённая конфигурация драйвера (например, токены API)
     *
     * @var array
     */
    protected array $protectedConfig;

    /**
     * Модель сервиса AML (опционально)
     *
     * @var AMLService|null
     */
    protected ?AMLService $service;

    /**
     * Конструктор драйвера GetBlockDriver.
     *
     * @param array $config
     * @param array $protectedConfig
     * @param AMLService|null $service
     */
    public function __construct(array $config, array $protectedConfig = [], AMLService $service = null)
    {
        $this->config = $config;
        $this->protectedConfig = $protectedConfig;
        $this->service = $service;
    }

    /**
     * Проверка транзакции через сервис GetBlock.
     *
     * @param array $params ['tx' => string, 'address' => string, 'currency' => string]
     *
     * @return AMLResponseInterface
     *
     * @throws Exception
     */
    public function checkTransaction(array $params): AMLResponseInterface
    {
        $responseData = $this->callRequest([
            'jsonrpc' => '2.0',
            'id' => time(),
            'method' => 'checkup.checktx',
            'params' => [
                'tx' => $params['tx'] ?? '',
                'addr' => $params['address'] ?? '',
                'currency' => $params['currency'] ?? '',
            ],
        ]);

        if (!isset($responseData['result']['check']['hash'])) {
            throw new AMLDriverException('Не удалось получить хеш проверки транзакции.');
        }


        Log::debug('--'.  json_encode($responseData));

        return $this->waitForResult($responseData['result']['check']['hash'], $this->config);
    }

    /**
     * Проверка адреса через сервис GetBlock.
     *
     * @param array $params ['address' => string, 'currency' => string]
     *
     * @return AMLResponseInterface
     *
     * @throws AMLDriverException
     */
    public function checkAddress(array $params): AMLResponseInterface
    {
        $responseData = $this->callRequest([
            'jsonrpc' => '2.0',
            'id' => time(),
            'method' => 'checkup.checkaddr',
            'params' => [
                'addr' => $params['address'] ?? '',
                'currency' => $params['currency'] ?? '',
            ],
        ]);

        if (!isset($responseData['result']['check']['hash'])) {
            throw new AMLDriverException('Не удалось получить хеш проверки адреса.');
        }

        return $this->waitForResult($responseData['result']['check']['hash'], $this->protectedConfig);
    }

    /**
     * Ожидает результата проверки по указанному хешу.
     *
     * @param string $txHash Хеш проверки
     * @param array $responseConfig Конфигурация для Response-класса
     *
     * @return AMLResponseInterface
     *
     * @throws AMLDriverException
     */
    protected function waitForResult(string $txHash, array $responseConfig): AMLResponseInterface
    {
        $maxAttempts = 5;
        $sleepSeconds = 3;

        $attempt = 0;

        do {
            sleep($sleepSeconds);

            try {
                $resultResponse = $this->callRequest([
                    'jsonrpc' => '2.0',
                    'id' => time(),
                    'method' => 'checkup.getresult',
                    'params' => ['hash' => $txHash],
                ]);
            } catch (Throwable $e) {
                if ($attempt >= $maxAttempts - 1) {
                    throw new AMLDriverException('Ошибка получения результатов AML: ' . $e->getMessage());
                }
                $attempt++;
                continue;
            }

            $status = $resultResponse['result']['check']['status'] ?? 'PENDING';
            $attempt++;

        } while ($status === 'PENDING' && $attempt < $maxAttempts);

        if ($status === 'PENDING') {
            throw new AMLDriverException('Превышено время ожидания результатов проверки AML.');
        }

        return new GetBlockResponse($resultResponse ?? [], $this->protectedConfig);
    }

    /**
     * Отправляет запрос к API GetBlock.
     *
     * @param array $options Параметры запроса
     *
     * @return mixed Ответ от API в виде массива
     *
     * @throws AMLDriverException Если запрос завершился с ошибкой
     */
    private function callRequest(array $options): mixed
    {
        $response = Http::baseUrl('https://api.getblock.net')
            ->withHeaders(['Content-Type' => 'application/json'])
            ->withToken($this->protectedConfig['token'] ?? '')
            ->post('/rpc/v1/request', $options);

        if ($response->failed()) {
            throw new AMLDriverException('Ошибка HTTP-запроса: код ' . $response->status());
        }

        $data = $response->json();

        if (isset($data['error'])) {
            throw new AMLDriverException('Ошибка API GetBlock: ' . json_encode($data['error'], JSON_UNESCAPED_UNICODE));
        }

        return $data;
    }
}
