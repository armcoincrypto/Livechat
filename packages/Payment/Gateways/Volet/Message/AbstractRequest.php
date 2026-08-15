<?php
declare(strict_types=1);

namespace iEXPackages\Payment\Gateways\Volet\Message;

use iEXPackages\Payment\Engines\Message\AbstractRequest as BaseAbstractRequest;

abstract class AbstractRequest extends BaseAbstractRequest
{
    /**
     * Email адрес владельца счета
     */
    public function getSciAccountEmail(): string
    {
        return $this->getParameter('sci_account_email');
    }

    /**
     * Название SCI
     */
    public function getSciName(): string
    {
        return $this->getParameter('sci_name');
    }

    /**
     * Пароль SCI
     */
    public function getSCIPassword(): string
    {
        return $this->getParameter('sci_password');
    }
}
