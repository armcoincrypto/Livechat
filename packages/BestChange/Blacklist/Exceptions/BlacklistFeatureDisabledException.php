<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Blacklist\Exceptions;

use RuntimeException;

/**
 * Функционал выключен в настройках.
 */
final class BlacklistFeatureDisabledException extends RuntimeException {}
