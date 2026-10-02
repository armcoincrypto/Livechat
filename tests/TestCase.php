<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Minimal Laravel-aware base for Unit tests.
 * Application bootstrap is performed by tests/bootstrap.php.
 *
 * Restores Event/Notification/Eloquent/Log dispatchers after each test so
 * Event::fake() cannot suppress model events (status logs, mail listeners).
 */
abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        if (isset($GLOBALS['__exs_phpunit_events']) && $GLOBALS['__exs_phpunit_events'] !== null) {
            $dispatcher = $GLOBALS['__exs_phpunit_events'];
            app()->instance('events', $dispatcher);
            Event::clearResolvedInstances();
            Event::swap($dispatcher);
            Model::setEventDispatcher($dispatcher);
            if (method_exists(Cache::getFacadeRoot(), 'refreshEventDispatcher')) {
                Cache::refreshEventDispatcher();
            }
            // Log::listen binds to the dispatcher active at listen-time; rebuild log.
            app()->forgetInstance('log');
            Log::clearResolvedInstances();
            if (method_exists(app('log'), 'setDispatcher')) {
                app('log')->setDispatcher($dispatcher);
            }
        }

        Notification::clearResolvedInstances();

        parent::tearDown();
    }
}
