<?php

declare(strict_types=1);

namespace Tests\Unit\TelegramOperator;

use App\Console\Commands\TelegramReconcileOrderCardsCommand;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class TelegramReconcileOrderCardsCommandTest extends TestCase
{
    public function test_apply_is_refused(): void
    {
        $code = Artisan::call('telegram:reconcile-order-cards', ['--apply' => true]);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('REFUSED', Artisan::output());
    }

    public function test_command_class_defaults_to_dry_run(): void
    {
        $src = (string) file_get_contents(base_path('app/Console/Commands/TelegramReconcileOrderCardsCommand.php'));
        $this->assertStringContainsString('--dry-run', $src);
        $this->assertStringContainsString('WOULD_CREATE', $src);
        $this->assertTrue(class_exists(TelegramReconcileOrderCardsCommand::class));
    }
}
