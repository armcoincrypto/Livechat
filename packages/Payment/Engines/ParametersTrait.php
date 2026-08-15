<?php

namespace iEXPackages\Payment\Engines;

use iEXPackages\Payment\Exception\InvalidRequestException;
use Symfony\Component\HttpFoundation\ParameterBag;

trait ParametersTrait
{
    /**
     * Internal storage of all of the parameters.
     */
    protected ParameterBag $parameters;

    /**
     * Set one parameter.
     *
     * @param  string  $key  Parameter key
     * @param  mixed  $value  Parameter value
     * @return $this
     */
    protected function setParameter(string $key, $value)
    {
        $this->parameters->set($key, $value);

        return $this;
    }

    /**
     * Get one parameter.
     *
     * @param  string  $key  Parameter key
     * @return mixed A single parameter value.
     */
    protected function getParameter(string $key)
    {
        return $this->parameters->get($key);
    }

    /**
     * Get all parameters.
     *
     * @return array An associative array of parameters.
     */
    public function getParameters(): array
    {
        return $this->parameters->all();
    }

    /**
     * Initialize the object with parameters.
     *
     * If any unknown parameters passed, they will be ignored.
     *
     * @param  array  $parameters  An associative array of parameters
     */
    public function initialize(array $parameters = []): ParametersTrait
    {
        $this->parameters = new ParameterBag;
        Helper::initialize($this, $parameters);

        return $this;
    }

    /**
     * Validate the request.
     *
     * This method is called internally by gateways to avoid wasting time with an API call
     * when the request is clearly invalid.
     *
     * @param string ... a variable length list of required parameters
     *
     * @throws InvalidRequestException
     */
    public function validate(...$args)
    {
        foreach ($args as $key) {
            if (! $this->parameters->has($key)) {
                throw new InvalidRequestException("The {$key} parameter is required");
            }
        }
    }
}
