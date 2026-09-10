<?php

declare(strict_types=1);

namespace Tests\Unit\Orders;

use App\Console\Commands\OrdersWaitingDepositHealthCommand;
use Illuminate\Console\Command;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Contract: scheduler must call a supported signature; exit 1 remains a
 * real health finding, not a missing --format=json option.
 */
final class WaitingDepositHealthCommandContractTest extends TestCase
{
    public function test_command_signature_accepts_format_json(): void
    {
        $ref = new ReflectionClass(OrdersWaitingDepositHealthCommand::class);
        $sig = $ref->getProperty('signature');
        $sig->setAccessible(true);
        $value = (string) $sig->getValue(new OrdersWaitingDepositHealthCommand());

        self::assertStringContainsString('orders:waiting-deposit-health', $value);
        self::assertStringContainsString('{--format=json', $value);
        self::assertStringNotContainsString('{--json', $value);
    }

    public function test_command_fails_only_when_new_payable_without_destination(): void
    {
        $src = (string) file_get_contents(
            dirname(__DIR__, 3) . '/app/Console/Commands/OrdersWaitingDepositHealthCommand.php'
        );
        self::assertStringContainsString('$ok = $newPayable === 0;', $src);
        self::assertStringContainsString('KIND_NEW_PAYABLE_ORDER_WITHOUT_DESTINATION', $src);
        self::assertStringContainsString('return $ok ? self::SUCCESS : self::FAILURE;', $src);
        self::assertSame(Command::SUCCESS, 0);
        self::assertSame(Command::FAILURE, 1);
    }

    public function test_unhealthy_payload_is_logged_as_error_not_only_info(): void
    {
        $src = (string) file_get_contents(
            dirname(__DIR__, 3) . '/app/Console/Commands/OrdersWaitingDepositHealthCommand.php'
        );
        self::assertStringContainsString("Log::error('orders:waiting-deposit-health'", $src);
        self::assertStringContainsString("'new_payable_public_ids' => \$newPayablePublicIds", $src);
        self::assertStringNotContainsString("'email'", explode("Log::error('orders:waiting-deposit-health'", $src, 2)[1] ?? '');
    }

    public function test_schedule_matches_command_signature_and_runs_in_background(): void
    {
        $src = (string) file_get_contents(
            dirname(__DIR__, 3) . '/app/Console/Schedule/ExchangeSchedule.php'
        );
        self::assertNotFalse(preg_match(
            "/\\\$schedule->command\\('orders:waiting-deposit-health --format=json'\\)\\s*"
            . "->everyFiveMinutes\\(\\)\\s*"
            . "->onOneServer\\(\\)\\s*"
            . "->withoutOverlapping\\(5\\)\\s*"
            . "->runInBackground\\(\\)\\s*"
            . "->appendOutputTo\\(storage_path\\('logs\\/orders_waiting_deposit_health\\.log'\\)\\);/s",
            $src
        ));
        self::assertSame(1, substr_count($src, "command('orders:waiting-deposit-health --format=json')"));
    }
}
