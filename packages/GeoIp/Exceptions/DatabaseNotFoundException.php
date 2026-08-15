<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\Exceptions;

/**
 * Файл базы MaxMind (.mmdb) не найден или недоступен.
 */
final class DatabaseNotFoundException extends GeoIpException {}
