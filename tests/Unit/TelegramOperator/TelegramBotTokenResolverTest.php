<?php

declare(strict_types=1);

namespace Tests\Unit\TelegramOperator;

use App\Notifications\TelegramNewOrder;
use App\Services\TelegramOperator\TelegramBotTokenResolver;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class TelegramBotTokenResolverTest extends TestCase
{
    public function test_explicit_operator_token_wins_over_notification_token(): void
    {
        Config::set('telegram_operator.bot_token', 'PRIMARY_OPERATOR_BOT_TOKEN');
        $resolver = new TelegramBotTokenResolver();
        $this->assertSame(
            'PRIMARY_OPERATOR_BOT_TOKEN',
            $resolver->resolve('SECONDARY_NOTIFICATION_TOKEN')
        );
    }

    public function test_falls_back_to_notification_token_when_env_empty(): void
    {
        Config::set('telegram_operator.bot_token', '');
        $resolver = new TelegramBotTokenResolver();
        $this->assertSame('SECONDARY_NOTIFICATION_TOKEN', $resolver->resolve('SECONDARY_NOTIFICATION_TOKEN'));
        $this->assertSame('', $resolver->resolve(null));
    }

    public function test_telegram_new_order_uses_token_resolver(): void
    {
        $src = (string) file_get_contents(base_path('app/Notifications/TelegramNewOrder.php'));
        $this->assertStringContainsString('TelegramBotTokenResolver', $src);
        $this->assertStringContainsString('->resolve(', $src);
    }
}
