<?php

declare(strict_types=1);

namespace Tests\Unit\Orders;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class OrderStatusMailJobClosureTest extends TestCase
{
    public function test_cron_handler_does_not_dispatch_missing_order_status_mail_job(): void
    {
        $bootstrap = File::get(base_path('packages/Transaction/Bindings/ManagersBootstrap.php'));
        // Comments may mention the retired class; runtime dispatch must stay gone.
        self::assertStringNotContainsString('dispatch(new OrderStatusMailJob', $bootstrap);
        self::assertStringNotContainsString('OrderStatusMailJob::', $bootstrap);
        self::assertStringContainsString('SmartMailer', $bootstrap);
    }

    public function test_managers_notification_does_not_import_missing_job(): void
    {
        $src = File::get(base_path('packages/Transaction/Bindings/ManagersNotification.php'));
        self::assertStringNotContainsString('OrderStatusMailJob', $src);
    }

    public function test_order_status_mail_job_class_file_absent_by_design(): void
    {
        self::assertFileDoesNotExist(base_path('app/Jobs/Order/OrderStatusMailJob.php'));
    }
}
