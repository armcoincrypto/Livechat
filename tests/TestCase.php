<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Minimal Laravel-aware base for Unit tests.
 * Application bootstrap is performed by tests/bootstrap.php.
 */
abstract class TestCase extends BaseTestCase
{
}
