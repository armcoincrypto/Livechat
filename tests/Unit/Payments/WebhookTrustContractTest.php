<?php

declare(strict_types=1);

namespace Tests\Unit\Payments;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class WebhookTrustContractTest extends TestCase
{
    public function test_callback_paid_path_uses_confirmed_inbound_transition(): void
    {
        $src = (string) file_get_contents(base_path('packages/Payments/Callback/Traits/CallbackPaymentHandlingTrait.php'));
        $this->assertStringContainsString('ConfirmedInboundPaidTransition', $src);
        $this->assertStringContainsString('replay_detected', $src);
        $this->assertStringContainsString('payment_transition_failed', $src);
        $this->assertDoesNotMatchRegularExpression(
            '/grantPaidWritePermit\(\);\s*\$transaction->setStatus\(\$finalStatus\)/s',
            $src
        );
    }

    public function test_no_raw_webhook_paid_writers_outside_transition(): void
    {
        $callback = (string) file_get_contents(base_path('packages/Payments/Callback/Traits/CallbackPaymentHandlingTrait.php'));
        $this->assertStringContainsString('ConfirmedInboundPaidTransition', $callback);
        $this->assertStringContainsString('setStatus(7)', $callback);

        $controller = (string) file_get_contents(base_path('app/Http/Controllers/Callbacks/MerchantCallbackController.php'));
        $this->assertStringNotContainsString('setStatus(7)', $controller);

        $kobbopay = (string) file_get_contents(base_path('app/Gateways/Crypto/Kobbopay/Services/KobbopayInboundWebhookService.php'));
        $this->assertStringNotContainsString('setStatus(7)', $kobbopay);
    }

    public function test_webhook_log_has_no_unique_constraint(): void
    {
        $idx = collect(DB::select('SHOW INDEX FROM merchant_transaction_webhooks'));
        $unique = $idx->contains(fn ($i) => (int) $i->Non_unique === 0 && $i->Key_name !== 'PRIMARY');
        $this->assertFalse($unique, 'do not add uniqueness until historical dupes are proven safe');
    }
}
