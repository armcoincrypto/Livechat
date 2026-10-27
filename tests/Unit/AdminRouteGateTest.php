<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\AdminRouteGate;
use PHPUnit\Framework\TestCase;

final class AdminRouteGateTest extends TestCase
{
    public function test_console_always_loads_admin_frontend(): void
    {
        $this->assertTrue(AdminRouteGate::shouldLoadAdminFrontend(true, '/client-api/v1/tech', 'iexadmin'));
        $this->assertTrue(AdminRouteGate::shouldLoadAdminFrontend(true, '/', 'iexadmin'));
    }

    public function test_client_api_and_public_paths_skip_admin_frontend(): void
    {
        $this->assertFalse(AdminRouteGate::shouldLoadAdminFrontend(false, '/client-api/v1/tech', 'iexadmin'));
        $this->assertFalse(AdminRouteGate::shouldLoadAdminFrontend(false, '/client-api/v1/rates/operations/USDTTRC20/SBERRUB', 'iexadmin'));
        $this->assertFalse(AdminRouteGate::shouldLoadAdminFrontend(false, '/apis/index.php', 'iexadmin'));
        $this->assertFalse(AdminRouteGate::shouldLoadAdminFrontend(false, '/', 'iexadmin'));
        $this->assertFalse(AdminRouteGate::shouldLoadAdminFrontend(false, '/start', 'iexadmin'));
    }

    public function test_admin_prefix_loads_admin_frontend(): void
    {
        $this->assertTrue(AdminRouteGate::shouldLoadAdminFrontend(false, '/iexadmin', 'iexadmin'));
        $this->assertTrue(AdminRouteGate::shouldLoadAdminFrontend(false, '/iexadmin/', 'iexadmin'));
        $this->assertTrue(AdminRouteGate::shouldLoadAdminFrontend(false, 'iexadmin/dashboard', 'iexadmin'));
        $this->assertTrue(AdminRouteGate::shouldLoadAdminFrontend(false, '/iexadmin/orders/1', 'iexadmin'));
    }

    public function test_similar_but_non_admin_prefix_is_skipped(): void
    {
        $this->assertFalse(AdminRouteGate::shouldLoadAdminFrontend(false, '/iexadminx', 'iexadmin'));
        $this->assertFalse(AdminRouteGate::shouldLoadAdminFrontend(false, '/iexad', 'iexadmin'));
        $this->assertFalse(AdminRouteGate::shouldLoadAdminFrontend(false, '/admin', 'iexadmin'));
    }

    public function test_empty_admin_folder_fails_open(): void
    {
        $this->assertTrue(AdminRouteGate::shouldLoadAdminFrontend(false, '/client-api/v1/tech', ''));
        $this->assertTrue(AdminRouteGate::shouldLoadAdminFrontend(false, '/', '  '));
    }
}
