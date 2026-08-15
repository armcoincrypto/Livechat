<?php

namespace iEXPackages\Payment\Engines\Message;

use App\Models\GatewayMerchant;
use App\Models\Task;
use iEXPackages\Order\Order;
use iEXPackages\Payment\Engines\CreditCard;
use iEXPackages\Payment\Engines\Helper;
use iEXPackages\Payment\Engines\Http\ClientInterface;
use iEXPackages\Payment\Engines\ItemBag;
use iEXPackages\Payment\Engines\ParametersTrait;
use iEXPackages\Payment\Exception\InvalidRequestException;
use iEXPackages\Payment\Exception\RuntimeException;
use iEXPackages\Transaction\Transaction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Money;
use Money\Number;
use Money\Parser\DecimalMoneyParser;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request as HttpRequest;

/**
 * Abstract Request
 *
 * This abstract class implements RequestInterface and defines a basic
 * set of functions that all Omnipay Requests are intended to include.
 *
 * Requests of this class are usually created using the createRequest
 * function of the gateway and then actioned using methods within this
 * class or a class that extends this class.
 *
 * Example -- creating a request:
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
 * Example -- validating and sending a request:
 *
 * <code>
 *   try {
 *     $myRequest->validate();
 *     $myResponse = $myRequest->send();
 *   } catch (InvalidRequestException $e) {
 *     print "Something went wrong: " . $e->getMessage() . "\n";
 *   }
 *   // now do something with the $myResponse object, test for success, etc.
 * </code>
 */
abstract class AbstractRequest implements RequestInterface
{
    use ParametersTrait {
        setParameter as traitSetParameter;
    }

    protected ISOCurrencies $currencies;

    protected bool $zeroAmountAllowed = true;

    protected bool $negativeAmountAllowed = false;

    /**
     * The request client.
     */
    protected ClientInterface $httpClient;

    /**
     * The HTTP request object.
     */
    protected HttpRequest $httpRequest;

    /**
     * An associated ResponseInterface.
     */
    protected ResponseInterface $response;

    /**
     * Create a new Request
     *
     * @param  ClientInterface  $httpClient  A HTTP client to make API calls with
     * @param  HttpRequest  $httpRequest  A Symfony HTTP request object
     */
    public function __construct(ClientInterface $httpClient, HttpRequest $httpRequest)
    {

        $this->httpClient = $httpClient;
        $this->httpRequest = $httpRequest;
        // $this->initialize();
    }

    /**
     * Initialize the object with parameters.
     *
     * If any unknown parameters passed, they will be ignored.
     *
     * @param  array  $parameters  An associative array of parameters
     * @return $this
     *
     * @throws RuntimeException
     */
    public function initialize(array $parameters = []): static
    {
        $this->parameters = new ParameterBag;
        Helper::initialize($this, $parameters);

        foreach ($parameters as $key => $value) {
            $this->parameters->set($key, $value);
        }

        return $this;
    }

    /**
     * Set a single parameter
     *
     * @param  string  $key  The parameter key
     * @param  mixed  $value  The value to set
     * @return $this
     *
     * @throws RuntimeException if a request parameter is modified after the request has been sent.
     */
    protected function setParameter(string $key, $value): static
    {
        return $this->traitSetParameter($key, $value);
    }

    /**
     * Gets the test mode of the request from the gateway.
     */
    public function getTestMode(): bool
    {
        return $this->getParameter('testMode');
    }

    /**
     * Sets the test mode of the request.
     *
     * @param  bool  $value  True for test mode on.
     * @return $this
     */
    public function setTestMode(bool $value): static
    {
        return $this->setParameter('testMode', $value);
    }

    /**
     * Get the card.
     */
    public function getCard(): CreditCard
    {
        return $this->getParameter('card');
    }

    /**
     * Sets the card.
     *
     * @return $this
     */
    public function setCard(CreditCard|array $value): static
    {
        if ($value && ! $value instanceof CreditCard) {
            $value = new CreditCard($value);
        }

        return $this->setParameter('card', $value);
    }

    /**
     * Get the card token.
     */
    public function getToken(): string
    {
        return $this->getParameter('token');
    }

    /**
     * Sets the card token.
     *
     * @return $this
     */
    public function setToken(string $value): static
    {
        return $this->setParameter('token', $value);
    }

    /**
     * Get the card reference.
     */
    public function getCardReference(): string
    {
        return $this->getParameter('cardReference');
    }

    /**
     * Sets the card reference.
     *
     * @return $this
     */
    public function setCardReference(string $value): static
    {
        return $this->setParameter('cardReference', $value);
    }

    /**
     * Validates and returns the formatted amount.
     */
    public function getAmount(): string
    {
        try {
            $money = $this->getMoney();

            if ($money !== null) {
                $moneyFormatter = new DecimalMoneyFormatter($this->getCurrencies());

                return $moneyFormatter->format($money);
            }
        } catch (\Exception $e) {
            return $this->getParameter('amount');
        }

        return $this->getParameter('amount');
    }

    /**
     * Записываем входные параметры заявки
     */
    public function setOrderData($order): AbstractRequest
    {
        return $this->setParameter('order_data', $order);
    }

    /**
     * Получение данные по заявке
     */
    public function getOrderData(): Task
    {
        return $this->getParameter('order_data');
    }

    public function setSellAdditionalFields($order): AbstractRequest
    {
        return $this->setParameter('sell_addition_fields', $order);
    }

    public function getSellAdditionalFields()
    {
        return $this->getParameter('sell_addition_fields');
    }

    public function setMerchantGateway($order): AbstractRequest
    {
        return $this->setParameter('merchant_gateways_data', $order);
    }

    /**
     * Получение данные по заявке
     */
    public function getMerchantGateway(): GatewayMerchant
    {
        return $this->getParameter('merchant_gateways_data');
    }

    public function getMerchantOptions(): array
    {
        return $this->getOrderData()->merchant->ext_options ?? [];
    }

    /**
     * Информация о дополнительных параметрах заявки
     */
    public function setPaymentExtraFields($tx): AbstractRequest
    {
        return $this->setParameter('payment_extra_fields', $tx);
    }

    /**
     * Получение деталей по заявке
     */
    public function getPaymentExtraFields(): Transaction
    {
        return $this->getParameter('payment_extra_fields');
    }

    /**
     * Sets the payment amount.
     *
     * @return $this
     */
    public function setAmount(float $value): static
    {
        return $this->setParameter('amount', $value);
    }

    /**
     * Get the payment currency code.
     */
    public function getCurrency(): string
    {
        return $this->getParameter('currency');
    }

    /**
     * Sets the payment currency code.
     *
     * @return $this
     */
    public function setCurrency(string $value): static
    {
        return $this->setParameter('currency', Str::upper($value));
    }

    /**
     * Get the request description.
     */
    public function getDescription(): string
    {
        return $this->getParameter('description');
    }

    /**
     * Sets the request description.
     *
     * @return $this
     */
    public function setDescription(string $value): static
    {
        return $this->setParameter('description', $value);
    }

    /**
     * Get the transaction ID.
     *
     * The transaction ID is the identifier generated by the merchant website.
     */
    public function getTransactionId(): mixed
    {
        return $this->getParameter('transactionId');
    }

    /**
     * Sets the transaction ID.
     *
     * @return $this
     */
    public function setTransactionId(string $value): static
    {
        return $this->setParameter('transactionId', $value);
    }

    /**
     * Get the transaction reference.
     *
     * The transaction reference is the identifier generated by the remote
     * payment gateway.
     */
    public function getTransactionReference(): string
    {
        return $this->getParameter('transactionReference');
    }

    /**
     * Sets the transaction reference.
     *
     * @return $this
     */
    public function setTransactionReference(string $value): static
    {
        return $this->setParameter('transactionReference', $value);
    }

    /**
     * A list of items in this order
     *
     * @return ItemBag|null A bag containing items in this order
     */
    public function getItems(): ?ItemBag
    {
        return $this->getParameter('items');
    }

    /**
     * Set the items in this order
     *
     * @param  array|ItemBag  $items  An array of items in this order
     * @return $this
     */
    public function setItems(ItemBag|array $items): static
    {
        if ($items && ! $items instanceof ItemBag) {
            $items = new ItemBag($items);
        }

        return $this->setParameter('items', $items);
    }

    /**
     * Get the client IP address.
     */
    public function getClientIp(): string
    {
        return $this->getParameter('clientIp');
    }

    /**
     * Sets the client IP address.
     *
     * @return $this
     */
    public function setClientIp(string $value): static
    {
        return $this->setParameter('clientIp', $value);
    }

    /**
     * Get the request return URL.
     */
    public function getReturnUrl(): string
    {
        return $this->getParameter('returnUrl');
    }

    /**
     * Sets the request return URL.
     *
     * @return $this
     */
    public function setReturnUrl(string $value): static
    {
        return $this->setParameter('returnUrl', $value);
    }

    /**
     * Get the request cancel URL.
     */
    public function getCancelUrl(): string
    {
        return $this->getParameter('cancelUrl');
    }

    /**
     * Sets the request cancel URL.
     *
     * @return $this
     */
    public function setCancelUrl(string $value): static
    {
        return $this->setParameter('cancelUrl', $value);
    }

    /**
     * Get the request notify URL.
     */
    public function getNotifyUrl(): string
    {
        return $this->getParameter('notifyUrl');
    }

    /**
     * Sets the request notify URL.
     *
     * @return $this
     */
    public function setNotifyUrl(string $value): static
    {
        return $this->setParameter('notifyUrl', $value);
    }

    /**
     * Get the payment issuer.
     *
     * This field is used by some European gateways, which support
     * multiple payment providers with a single API.
     */
    public function getPaymentMethod(): string
    {
        return $this->getParameter('paymentMethod');
    }

    /**
     * Set the payment method.
     *
     * This field is used by some European gateways, which support
     * multiple payment providers with a single API.
     *
     * @return $this
     */
    public function setPaymentMethod(string $value): static
    {
        return $this->setParameter('paymentMethod', $value);
    }

    /**
     * Send the request
     */
    public function send(): ResponseInterface
    {
        $data = $this->getData();

        return $this->sendData($data);
    }

    /**
     * Get the associated Response.
     */
    public function getResponse(): ResponseInterface
    {
        if ($this->response === null) {
            throw new RuntimeException('You must call send() before accessing the Response!');
        }

        return $this->response;
    }

    protected function getCurrencies(): ISOCurrencies
    {
        $this->currencies = new ISOCurrencies();

        return $this->currencies;
    }

    /**
     * Получаем валюту
     *
     * @param bool $default_currency
     * @return string
     */
    protected function getNetworkCodeForCurrency(bool $default_currency = true): string
    {
        $order = $this->getOrderData();
        $merchant = $this->getMerchantGateway();


        // 1) Явный код в настройках мерчанта
        $explicit = (string) (($merchant?->ext_options['network_code_currency'] ?? '') ?: '');
        if ($explicit !== '') {
            return $explicit;
        }

        $direction = $order->direction_exchange ?? null;
        $currency  = $direction?->currency1 ?? null;

        // 2) Код на уровне направления (только при наличии активных мерчантов)
        if ($direction) {
            $directionCode = (string) ($direction->network_code ?? '');
            if ($directionCode !== '') {
                $hasActiveMerchants = $direction->relationLoaded('merchants')
                    ? $direction->merchants->where('status', 1)->isNotEmpty()
                    : $direction->merchants()->where('status', 1)->exists();

                if ($hasActiveMerchants) {
                    return $directionCode;
                }
            }
        }


        // 3) Переопределение на уровне валюты через pivot для текущего мерчанта
        $merchantId = (int) ($merchant->id ?? 0);
        if ($currency && $merchantId > 0) {
            if ($currency->relationLoaded('merchants')) {
                $related = $currency->merchants
                    ->first(static fn ($m) => (int) ($m->pivot->gateway_merchant_id ?? 0) === $merchantId);

                $pivotCode = (string) ($related->pivot->network_code ?? '');
                if ($pivotCode !== '') {
                    return $pivotCode;
                }
            } else {
                $pivotCode = (string) $currency->merchants()
                    ->where('currency_merchants.gateway_merchant_id', $merchantId)
                    ->value('currency_merchants.network_code');

                if ($pivotCode !== '') {
                    return $pivotCode;
                }
            }
        }


        // 4) Код на самой валюте
        if ($currency) {
            $currencyCode = (string) ($currency->network_code ?? '');
            if ($currencyCode !== '') {
                return $currencyCode;
            }
        }

        // 5) Фолбэк
        return $default_currency ? (string) $this->getCurrency() : '';
    }

    protected function getMerchantNetworkCode(bool $default_currency = true): string
    {
        $response = $this->getNetworkCodeForCurrency($default_currency);
        $this->getOrderData()->meta->update(['merchant_network_code' => $response]);

        return $response;
    }


    /**
     * @throws InvalidRequestException
     */
    private function getMoney(int|string|null $amount = null): ?Money
    {
        $currencyCode = $this->getCurrency() ?: 'USD';
        $currency = new Currency($currencyCode);

        $amount = $amount !== null ? $amount : $this->getParameter('amount');

        if ($amount === null) {
            return null;
        } elseif ($amount instanceof Money) {
            $money = $amount;
        } elseif (is_int($amount)) {
            $money = new Money($amount, $currency);
        } else {
            $moneyParser = new DecimalMoneyParser($this->getCurrencies());

            $number = Number::fromString($amount);

            // Check for rounding that may occur if too many significant decimal digits are supplied.
            $decimal_count = strlen($number->getFractionalPart());
            $subunit = $this->getCurrencies()->subunitFor($currency);
            if ($decimal_count > $subunit) {
                throw new InvalidRequestException('Amount precision is too high for currency.');
            }

            $money = $moneyParser->parse((string) $number, $currency);
        }

        // Check for a negative amount.
        if (! $this->negativeAmountAllowed && $money->isNegative()) {
            throw new InvalidRequestException('A negative amount is not allowed.');
        }

        // Check for a zero amount.
        if (! $this->zeroAmountAllowed && $money->isZero()) {
            throw new InvalidRequestException('A zero amount is not allowed.');
        }

        return $money;
    }
}
