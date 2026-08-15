<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Exceptions;

use InvalidArgumentException;

/**
 * Бросается, когда входные параметры Request-а некорректны
 * или отсутствуют обязательные поля.
 */
class InvalidRequestException extends InvalidArgumentException
{
}
