<?php

namespace iEXPackages\Payment\Engines;

use App\Models\GatewayMerchant;
use App\Models\GatewayPayment;
use App\Models\Task;
use iEXPackages\Payment\Exception\RuntimeException;
use Symfony\Component\HttpFoundation\ParameterBag;

abstract class AbstractAPIRequest
{
    use ParametersTrait {
        setParameter as traitSetParameter;
    }

    protected string $typeLog = 'merchant';

    protected string $baseUrl = '';

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
     * Записываем входные параметры заявки
     *
     * @param Task $order
     * @return AbstractAPIRequest
     */
    public function setOrderData(Task $order): static
    {
        return $this->setParameter('order_data', $order);
    }

    /**
     * Получение данные по заявке
     *
     * @return Task
     */
    public function getOrderData(): Task
    {
        return $this->getParameter('order_data');
    }

    /**
     * Записываем входные параметры заявки
     *
     * @param GatewayPayment $order
     * @return AbstractAPIRequest
     */
    public function setPayGateway(GatewayPayment $order): static
    {
        return $this->setParameter('pay_gateway_data', $order);
    }

    /**
     * Получение данные по заявке
     *
     * @return GatewayPayment
     */
    public function getPayGateway(): GatewayPayment
    {
        return $this->getParameter('pay_gateway_data');
    }

    /**
     * Записываем входные параметры заявки
     *
     * @param GatewayMerchant $order
     * @return AbstractAPIRequest
     */
    public function setMerchantGateway(GatewayMerchant $order): static
    {
        return $this->setParameter('merchant_gateway_data', $order);
    }

    /**
     * Получение данные по заявке
     *
     * @return GatewayMerchant
     */
    public function getMerchantGateway(): GatewayMerchant
    {
        return $this->getParameter('merchant_gateway_data');
    }


    protected function createLogRequest(array $options = [])
    {

        if ((int) iEXSetting('is_disabled_log_request_autopayment') == 1) {
            return;
        }

        if($this->typeLog == 'pay') {

            try {
                $merchantPay = $this->getPayGateway();
                $order = $this->getOrderData();

            }catch (\Throwable $throwable) {
                //
            }
        }
    }
}
