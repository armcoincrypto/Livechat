<?php

namespace iEXPackages\Payment\Engines;

use iEXPackages\Payment\Engines\Http\Client;
use iEXPackages\Payment\Engines\Http\ClientInterface;
use iEXPackages\Payment\Engines\Interfaces\GatewayInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request as HttpRequest;

/**
 * Base payment gateway class
 *
 * This abstract class should be extended by all payment gateways
 * throughout the Omnipay system.  It enforces implementation of
 * the GatewayInterface interface and defines various common attributes
 * and methods that all gateways should have.
 *
 * Example:
 *
 * <code>
 *   // Initialise the gateway
 *   $gateway->initialize(...);
 *
 *   // Get the gateway parameters.
 *   $parameters = $gateway->getParameters();
 *
 *   // Create a credit card object
 *   $card = new CreditCard(...);
 *
 *   // Do an authorisation transaction on the gateway
 *   if ($gateway->supportsAuthorize()) {
 *       $gateway->authorize(...);
 *   } else {
 *       throw new \Exception('Gateway does not support authorize()');
 *   }
 * </code>
 *
 * For further code examples see the *omnipay-example* repository on github.
 */
abstract class AbstractGateway implements GatewayInterface
{
    use ParametersTrait {
        setParameter as traitSetParameter;
        getParameter as traitGetParameter;
    }

    protected string $name;

    /**
     * HTTP-клиент для работы с внешними API.
     */
    protected ClientInterface $httpClient;

    /**
     * Текущий HTTP-запрос (Symfony/Laravel).
     */
    protected HttpRequest $httpRequest;

    /**
     * Create a new gateway instance.
     */
    public function __construct(
        ?ClientInterface $httpClient = null,
        ?HttpRequest $httpRequest = null,
        array $parameters = []
    ) {
        // Если внешний клиент не передали — используем дефолтный (твой новый Client на Guzzle)
        $this->httpClient = $httpClient ?? $this->getDefaultHttpClient();

        // Если запрос не передали — берём из глобалов (в Laravel это тот же Symfony Request)
        $this->httpRequest = $httpRequest ?? $this->getDefaultHttpRequest();

//        // Если при создании шлюза передали параметры — инициализируем
//        if ($parameters !== []) {
//            $this->initialize($parameters);
//        }

        // Динамические параметры конкретного шлюза (если в нём есть такой метод)
        if (method_exists($this, 'dynamicParameters')) {
            $this->dynamicParameters();
        }
    }

    /**
     * Получить имя шлюза
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Получить пространства имени мерчтанта по умолчанию
     */
    public function getPurchaseRequest(): string
    {
        return "iEXPackages\\Payment\\Gateways\\{$this->getName()}\\Message\\PurchaseRequest";
    }

    /**
     * Получить пространства имени мерчтанта по умолчанию
     */
    public function getCompletePurchase(): string
    {
        return "iEXPackages\\Payment\\Gateways\\{$this->name}\\Message\\CompletePurchaseRequest";
    }

    /**
     * Get the short name of the Gateway
     *
     * @return string
     */
    public function getShortName()
    {
        return Helper::getGatewayShortName(get_class($this));
    }

    /**
     * Initialize this gateway with default parameters
     *
     * @return $this
     */
    public function initialize(array $parameters = [])
    {
        $this->parameters = new ParameterBag;
        // set default parameters
        foreach ($parameters as $key => $value) {
            if (is_array($value)) {
                $this->parameters->set($key, reset($value));
            } else {
                $this->parameters->set($key, $value);
            }
        }

        Helper::initialize($this, $parameters);

        return $this;
    }

    /**
     * @return array
     */
    public function getDefaultParameters()
    {
        return [];
    }

    /**
     * @param  string  $key
     * @return mixed
     */
    public function getParameter($key)
    {
        return $this->traitGetParameter($key);
    }

    /**
     * @param  string  $key
     * @param  mixed  $value
     * @return $this
     */
    public function setParameter($key, $value)
    {
        return $this->traitSetParameter($key, $value);
    }

    /**
     * @return bool
     */
    public function getTestMode()
    {
        return $this->getParameter('testMode');
    }

    /**
     * @param  bool  $value
     * @return $this
     */
    public function setTestMode($value)
    {
        return $this->setParameter('testMode', $value);
    }

    /**
     * @return string
     */
    public function getCurrency()
    {
        return strtoupper($this->getParameter('currency'));
    }

    /**
     * @param  string  $value
     * @return $this
     */
    public function setCurrency($value)
    {
        return $this->setParameter('currency', $value);
    }

    /**
     * Ищем базовый класс у каждой платежной системы
     *
     * @return mixed
     */
    protected function apiService(array $parameters = [])
    {
        $class_name = Str::studly($this->name);

        $class = '\\iEXPackages\\Payment\\Gateways\\'.$class_name.'\\APIRequest';

        if (class_exists($class)) {
            return new $class($parameters);
        }


        throw new InvalidArgumentException("Service [$this->name] not supported.");
    }

    /**
     * Supports Authorize
     *
     * @return bool True if this gateway supports the authorize() method
     */
    public function supportsAuthorize()
    {
        return method_exists($this, 'authorize');
    }

    /**
     * Supports Complete Authorize
     *
     * @return bool True if this gateway supports the completeAuthorize() method
     */
    public function supportsCompleteAuthorize()
    {
        return method_exists($this, 'completeAuthorize');
    }

    /**
     * Supports Capture
     *
     * @return bool True if this gateway supports the capture() method
     */
    public function supportsCapture()
    {
        return method_exists($this, 'capture');
    }

    /**
     * Supports Purchase
     *
     * @return bool True if this gateway supports the purchase() method
     */
    public function supportsPurchase()
    {
        return method_exists($this, 'purchase');
    }

    /**
     * Supports Complete Purchase
     *
     * @return bool True if this gateway supports the completePurchase() method
     */
    public function supportsCompletePurchase()
    {
        return method_exists($this, 'completePurchase');
    }

    /**
     * Supports Fetch Transaction
     *
     * @return bool True if this gateway supports the fetchTransaction() method
     */
    public function supportsFetchTransaction()
    {
        return method_exists($this, 'fetchTransaction');
    }

    /**
     * Supports Refund
     *
     * @return bool True if this gateway supports the refund() method
     */
    public function supportsRefund()
    {
        return method_exists($this, 'refund');
    }

    /**
     * Supports Void
     *
     * @return bool True if this gateway supports the void() method
     */
    public function supportsVoid()
    {
        return method_exists($this, 'void');
    }

    /**
     * Supports AcceptNotification
     *
     * @return bool True if this gateway supports the acceptNotification() method
     */
    public function supportsAcceptNotification()
    {
        return method_exists($this, 'acceptNotification');
    }

    /**
     * Supports CreateCard
     *
     * @return bool True if this gateway supports the create() method
     */
    public function supportsCreateCard()
    {
        return method_exists($this, 'createCard');
    }

    /**
     * Supports DeleteCard
     *
     * @return bool True if this gateway supports the delete() method
     */
    public function supportsDeleteCard()
    {
        return method_exists($this, 'deleteCard');
    }

    /**
     * Supports UpdateCard
     *
     * @return bool True if this gateway supports the update() method
     */
    public function supportsUpdateCard()
    {
        return method_exists($this, 'updateCard');
    }

    /**
     * Create and initialize a request object
     *
     * This function is usually used to create objects of type
     * Omnipay\Common\Message\AbstractRequest (or a non-abstract subclass of it)
     * and initialise them with using existing parameters from this gateway.
     *
     * Example:
     *
     * <code>
     *   class MyRequest extends \Omnipay\Common\Message\AbstractRequest {};
     *
     *   class MyGateway extends \Omnipay\Common\AbstractGateway {
     *     function myRequest($parameters) {
     *       $this->createRequest('MyRequest', $parameters);
     *     }
     *   }
     *
     *   // Create the gateway object
     *   $gw = Omnipay::create('MyGateway');
     *
     *   // Create the request object
     *   $myRequest = $gw->myRequest($someParameters);
     * </code>
     *
     * @param  string  $class  The request class name
     * @return \iEXPackages\Payment\Engines\Message\AbstractRequest
     */
    protected function createRequest($class, array $parameters)
    {
        $obj = new $class($this->httpClient, $this->httpRequest);

        return $obj->initialize(array_replace($this->getParameters(), $parameters));
    }

    /**
     * Create and initialize a request object
     *
     * @return mixed
     */
    protected function createApiRequest($class, array $parameters)
    {
        return new $class(array_replace($this->getParameters(), $parameters));
    }

    /**
     * Get the global default HTTP client.
     *
     * @return ClientInterface
     */
    protected function getDefaultHttpClient()
    {
        return new Client();
    }

    /**
     * Get the global default HTTP request.
     *
     * @return HttpRequest
     */
    protected function getDefaultHttpRequest()
    {
        return HttpRequest::createFromGlobals();
    }
}
