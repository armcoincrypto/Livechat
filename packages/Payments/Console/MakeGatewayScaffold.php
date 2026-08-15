<?php
declare(strict_types=1);

namespace iEXPackages\Payments\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final class MakeGatewayScaffold extends Command
{
    protected $signature = 'gateways:make
        {name : Имя шлюза (например Rapira, Heleket, Stripe)}
        {--alias= : Alias шлюза (например rapira). По умолчанию snake_case от name}
        {--type=crypto : crypto|fiat (по умолчанию crypto)}
        {--force : Перезаписать файлы если уже существуют}';

    protected $description = 'Создаёт структуру и шаблонные файлы для нового платёжного шлюза';

    public function handle(Filesystem $fs): int
    {
        $name = (string) $this->argument('name');
        $type = strtolower((string) $this->option('type'));

        if (!in_array($type, ['crypto', 'fiat'], true)) {
            $this->error('Параметр --type должен быть crypto или fiat');
            return self::FAILURE;
        }

        $studly = $this->studly($name);
        $alias  = (string) ($this->option('alias') ?: $this->snake($studly));

        $basePath = base_path("app/Gateways/" . ucfirst($type) . "/{$studly}");
        $force = (bool) $this->option('force');

        // Папки
        $dirs = [
            $basePath,
            $basePath . '/Messages',
            $basePath . '/Messages/Traits',
            $basePath . '/Services',
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                $fs->makeDirectory($dir, 0777, true);
            }
        }

        // Файлы
        $files = [
            $basePath . '/Gateway.php'                         => $this->tplGateway($type, $studly, $alias),
            $basePath . '/config.php'                          => $this->tplConfig($type, $studly, $alias),
            $basePath . '/Services/HttpClient.php'             => $this->tplHttpClient($type, $studly),
            $basePath . '/Messages/AbstractRequest.php'        => $this->tplMessagesAbstractRequest($type, $studly),
            $basePath . '/Messages/PurchaseRequest.php'        => $this->tplPurchaseRequest($type, $studly),
            $basePath . '/Messages/PurchaseResponse.php'       => $this->tplPurchaseResponse($type, $studly),
        ];

        foreach ($files as $path => $content) {
            if (file_exists($path) && !$force) {
                $this->warn("SKIP: {$path} (exists, use --force to overwrite)");
                continue;
            }

            $fs->put($path, $content);
            $this->info("OK: {$path}");
        }

        $this->info("Gateway scaffold created: App\\Gateways\\" . ucfirst($type) . "\\{$studly}");
        return self::SUCCESS;
    }

    // ----------------- helpers -----------------

    private function studly(string $value): string
    {
        return Str::studly($value);
    }

    private function snake(string $value): string
    {
        // Убираем всё кроме букв и цифр
        $value = preg_replace('/[^a-zA-Z0-9]+/', '', $value) ?? $value;

        return strtolower($value);
    }

    // ----------------- templates -----------------

    private function tplGateway(string $type, string $name, string $alias): string
    {
        $ns = "App\\Gateways\\" . ucfirst($type) . "\\{$name}";
        $category = $type;

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$ns};

use iEXPackages\\Payments\\Core\\Engine\\AbstractGateway;

/**
 * Шлюз: {$name}
 * Alias: {$alias}
 * Category: {$category}
 *
 * Важно:
 * - Операции берутся из config.php -> operations.
 * - Если включён auto_operations, методы purchase/payout/fetchPayment/fetchPayout
 *   будут доступны автоматически через __call() в AbstractGateway.
 *
 * @method \\iEXPackages\\Payments\\Core\\Contracts\\RequestInterface purchase(array \$parameters = [])
 * @method \\iEXPackages\\Payments\\Core\\Contracts\\RequestInterface fetchPayment(array \$parameters = [])
 * @method \\iEXPackages\\Payments\\Core\\Contracts\\RequestInterface payout(array \$parameters = [])
 * @method \\iEXPackages\\Payments\\Core\\Contracts\\RequestInterface fetchPayout(array \$parameters = [])
 */
final class Gateway extends AbstractGateway
{
    protected string \$name = '{$name}';
}

PHP;
    }

    private function tplConfig(string $type, string $name, string $alias): string
    {
        $category = $type;

        // Важно: namespaces классов опираются на структуру, которую мы создаём
        $baseNs = "App\\Gateways\\" . ucfirst($type) . "\\{$name}\\Messages";

        return <<<PHP
<?php

use {$baseNs}\\PurchaseRequest;
use {$baseNs}\\PurchaseResponse;

return [
    'schema' => 1,

    'meta' => [
        'name'        => '{$name} {$category} Gateway',
        'alias'       => '{$alias}',
        'version'     => '1.0.0',
        'description' => 'Шаблон шлюза {$name}. Заполни описание.',
        'category'    => '{$category}',

        // UI
        'sorting'     => 100,
        'recommended' => false,
    ],

    'capabilities' => [
        'incoming' => true,
        'outgoing' => false,

        'features' => [
            'is_crypto'         => {$this->boolStr($type === 'crypto')},
            'supports_balance'  => false,
            'supports_polling'  => false,
            'supports_callback' => false,

            // если хочешь авто-операции через __call()
            'auto_operations'   => true,
        ],

        'callbacks' => [
            'ipn'        => false,
            'return_url' => false,
            'cancel_url' => false,
        ],
    ],

    'operations' => [
        'purchase' => [
            'type'           => 'incoming',
            'label'          => 'Создание платежа',
            'description'    => 'Создаёт платёж и возвращает результат.',
            'request_class'  => PurchaseRequest::class,
            'response_class' => PurchaseResponse::class,
        ],
    ],

    'inputs' => [
        'merchant' => [
            'fields' => [
                // пример:
                // ['type'=>'input','label'=>'API Key','key'=>'api_key','is_hidden'=>true,'value_type'=>'string','required'=>true],
            ],
            'options_fields' => [
                // пример:
                // ['type'=>'select','label'=>'Network','key'=>'network','options'=>['TRC20'=>'TRC20'],'value_type'=>'string'],
            ],
        ],

        'pay' => [
            'fields' => [
                // payout-параметры (если шлюз поддерживает outgoing)
            ],
            'options_fields' => [
            ],
        ],
    ],
];

PHP;
    }

    private function tplHttpClient(string $type, string $name): string
    {
        $ns = "App\\Gateways\\" . ucfirst($type) . "\\{$name}\\Services";

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$ns};

use Exception;
use Illuminate\\Http\\Client\\ConnectionException;
use Illuminate\\Support\\Arr;
use Illuminate\\Support\\Facades\\Http;
use Illuminate\\Support\\Facades\\Log;
use Throwable;

/**
 * HttpClient для шлюза {$name}.
 *
 * Здесь ты концентрируешь:
 * - baseUrl/endpoint
 * - заголовки/токены/подпись
 * - timeout/retry
 * - логирование
 * - обработку исключений
 */
final class HttpClient
{
    public function __construct(
        private readonly string \$baseUrl,
        private readonly int \$timeoutSeconds = 10,
        private readonly int \$maxRetries = 3,
        private readonly int \$retryDelayMs = 1000,
    ) {}

    /**
     * Выполнить HTTP запрос к API шлюза.
     *
     * @param string \$method get|post|put|delete
     * @param string \$path   /api/path
     * @param array  \$data
     * @param string \$format asJson|asForm
     * @param string|null \$transactionId
     *
     * @throws Throwable
     */
    public function request(string \$method, string \$path, array \$data = [], string \$format = 'asJson', ?string \$transactionId = null): array
    {
        try {
            \$http = Http::timeout(\$this->timeoutSeconds)
                ->retry(\$this->maxRetries, \$this->retryDelayMs)
                ->baseUrl(rtrim(\$this->baseUrl, '/'));

            \$http = \$format === 'asForm' ? \$http->asForm() : \$http->asJson();

            \$response = \$http->{\$method}(\$path, \$data)->throw()->json();

            Log::info('Gateway API Request', [
                'transaction_id' => \$transactionId,
                'method' => strtoupper(\$method),
                'url' => \$path,
                'request_data' => Arr::except(\$data, ['private_key', 'token']),
                'response' => \$response,
            ]);

            return is_array(\$response) ? \$response : [];

        } catch (ConnectionException \$e) {
            Log::error('Ошибка соединения с API', [
                'transaction_id' => \$transactionId,
                'error' => \$e->getMessage(),
                'path' => \$path,
            ]);

            throw new Exception('Ошибка соединения с сервисом.');

        } catch (Throwable \$e) {
            Log::critical('Критическая ошибка API', [
                'transaction_id' => \$transactionId,
                'error' => \$e->getMessage(),
                'path' => \$path,
            ]);

            throw new Exception('Критическая ошибка в API.');
        }
    }
}

PHP;
    }

    private function tplMessagesAbstractRequest(string $type, string $name): string
    {
        $ns = "App\\Gateways\\" . ucfirst($type) . "\\{$name}\\Messages";
        $svcNs = "App\\Gateways\\" . ucfirst($type) . "\\{$name}\\Services";

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$ns};

use iEXPackages\\Payments\\Core\\Engine\\AbstractRequest as BaseAbstractRequest;
use {$svcNs}\\HttpClient;

/**
 * Базовый Request для шлюза {$name}.
 *
 * Здесь:
 * - подключаются Generated*Inputs (позже генератором)
 * - задаётся базовый endpoint (можно статически или из конфига)
 * - создаётся HttpClient и единый callApi() для всех операций
 */
abstract class AbstractRequest extends BaseAbstractRequest
{
    // Пример подключения (когда сгенерируешь):
    // use Traits\\GeneratedMerchantInputs;

    protected ?HttpClient \$httpClient = null;

    /**
     * Базовый URL (по умолчанию шаблонный).
     *
     * В каждом шлюзе ты сам решаешь:
     * - статический хост: вернуть 'https://example.com'
     * - из конфига: return 'https://' . \$this->configString('api_host')
     * - кастомно: переопределить как нужно
     */
    protected function endpointStaticBaseUrl(): string
    {
        return 'https://example.com';
    }

    /**
     * HttpClient создаётся один раз и используется во всех Request-ах.
     */
    protected function http(): HttpClient
    {
        if (\$this->httpClient instanceof HttpClient) {
            return \$this->httpClient;
        }

        \$this->httpClient = new HttpClient(
            baseUrl: \$this->endpointUrl('/'),
        );

        return \$this->httpClient;
    }

    /**
     * Единый вызов API.
     *
     * @throws \\Throwable
     */
    protected function callApi(string \$method, string \$path, array \$data = [], string \$format = 'asJson'): array
    {
        \$transactionId = \$this->getTransactionId();

        return \$this->http()->request(
            method: \$method,
            path: \$path,
            data: \$data,
            format: \$format,
            transactionId: \$transactionId !== null ? (string) \$transactionId : null
        );
    }
}

PHP;
    }

    private function tplPurchaseRequest(string $type, string $name): string
    {
        $ns = "App\\Gateways\\" . ucfirst($type) . "\\{$name}\\Messages";

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$ns};

use iEXPackages\\Payments\\Core\\Contracts\\ResponseInterface;

/**
 * Создание платежа (incoming).
 */
final class PurchaseRequest extends AbstractRequest
{
    public function getData(): array
    {
        // Пример: минимальная валидация
        // \$this->validate('amount', 'currency');

        return [
            // 'amount' => (string) \$this->getParameter('amount'),
            // 'currency' => (string) \$this->getParameter('currency'),
        ];
    }

    protected function sendData(array \$data): ResponseInterface
    {
        // TODO: замени endpoint на реальный
        \$response = \$this->callApi('post', '/purchase', \$data, 'asJson');

        return \$this->response = new PurchaseResponse(
            \$this,
            is_array(\$response) ? \$response : [],
            \$data
        );
    }
}

PHP;
    }

    private function tplPurchaseResponse(string $type, string $name): string
    {
        $ns = "App\\Gateways\\" . ucfirst($type) . "\\{$name}\\Messages";

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$ns};

use iEXPackages\\Payments\\Core\\Engine\\AbstractResponse;

/**
 * Ответ на создание платежа (incoming).
 */
final class PurchaseResponse extends AbstractResponse
{
    /**
     * Базовая логика успешности (шаблон).
     * TODO: замени под реальный ответ API.
     */
    public function isSuccessful(): bool
    {
        // пример: если есть id
        return \$this->dataHas('id') && is_numeric(\$this->dataGet('id')) && (int)\$this->dataGet('id') > 0;
    }
}

PHP;
    }

    private function boolStr(bool $v): string
    {
        return $v ? 'true' : 'false';
    }
}
