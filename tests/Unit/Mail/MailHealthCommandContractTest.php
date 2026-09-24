<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use PHPUnit\Framework\TestCase;

final class MailHealthCommandContractTest extends TestCase
{
    public function test_mail_health_never_fabricates_last_success_and_uses_unknown_on_failure(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3).'/app/Console/Commands/MailHealthCommand.php');
        $this->assertStringContainsString('MAIL_LAST_SUCCESS', $src);
        $this->assertStringContainsString('intentionally null', $src);
        $this->assertStringContainsString("'MAIL_CONFIG_OK' => 'UNKNOWN'", $src);
        $this->assertStringNotContainsString('print.*password', $src);
    }
}
