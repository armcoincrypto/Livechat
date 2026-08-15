<?php

namespace iEXPackages\Payment\Engines\Message;

/**
 * Request Interface
 *
 * This interface class defines the standard functions that any Omnipay request
 * interface needs to be able to provide.  It is an extension of MessageInterface.
 */
interface RequestInterface extends MessageInterface
{
    /**
     * Initialize request with parameters
     *
     * @param  array  $parameters  The parameters to send
     */
    public function initialize(array $parameters = []);

    /**
     * Get all request parameters
     */
    public function getParameters(): array;

    /**
     * Get the response to this request (if the request has been sent)
     */
    public function getResponse(): ResponseInterface;

    /**
     * Send the request
     */
    public function send(): ResponseInterface;

    /**
     * Send the request with specified data
     *
     * @param  array  $data  The data to send
     */
    public function sendData(array $data): ResponseInterface;
}
