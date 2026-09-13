<?php

declare(strict_types=1);

namespace Tests\Unit\Orders;

use App\Models\Task;
use App\Services\Certification\CertificationContext;
use App\Services\Certification\CertificationDestinationIssuer;
use App\Services\Certification\CertificationForbiddenKobbopayHttpClient;
use App\Services\Certification\CertificationGate;
use App\Services\Certification\CertificationKobbopayForbiddenException;
use App\Services\Certification\CertificationOrderMarker;
use App\Services\Certification\CertificationPaymentSimulator;
use App\Services\Orders\InboundPaymentDestinationGuard;
use App\Services\Orders\OrderCreatedTelegramNotifier;
use App\Services\Orders\PaymentDestinationRouter;
use iEXPackages\Payments\Core\Contracts\GatewayHttpClientInterface;
use iEXPackages\Payments\Logging\GatewayLogContext;
use iEXPackages\SmartMailer\Conditions\OrderCreatedCondition;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Default-off order-lifecycle certification rail.
 * No production task inserts. No real Kobbopay HTTP.
 */
final class SafeOrderCertificationHarnessTest extends TestCase
{
    protected function tearDown(): void
    {
        config([
            'exs_order_certification.enabled' => false,
            'exs_order_certification.token' => '',
            'exs_order_certification.provider' => 'INTERNAL_FAKE',
        ]);
        app(CertificationContext::class)->deactivate();
        parent::tearDown();
    }

    public function test_certification_disabled_is_rejected(): void
    {
        config([
            'exs_order_certification.enabled' => false,
            'exs_order_certification.token' => str_repeat('t', 16),
        ]);
        $eval = app(CertificationGate::class)->evaluate();
        $this->assertFalse($eval['allowed']);
        $this->assertSame('flag_disabled', $eval['reason']);
        $this->assertFalse((bool) config('exs_order_certification.enabled'));
    }

    public function test_unauthorized_request_blocked(): void
    {
        $this->enableCertConfig();
        $request = $this->certRequest('lifecycle@qa.exswaping.com', str_repeat('t', 16));
        $eval = app(CertificationGate::class)->evaluate($request);
        $this->assertFalse($eval['allowed']);
        $this->assertSame('operator_context_missing', $eval['reason']);
    }

    public function test_wrong_token_blocked(): void
    {
        $this->enableCertConfig();
        app(CertificationContext::class)->activate(
            CertificationContext::SCENARIO_SUCCESS,
            'lifecycle@qa.exswaping.com',
            'wrong-token-value-xx'
        );
        $eval = app(CertificationGate::class)->evaluate();
        $this->assertFalse($eval['allowed']);
        $this->assertSame('wrong_token', $eval['reason']);
    }

    public function test_non_qa_identity_blocked(): void
    {
        $this->enableCertConfig();
        app(CertificationContext::class)->activate(
            CertificationContext::SCENARIO_SUCCESS,
            'customer@example.com',
            str_repeat('t', 16)
        );
        $eval = app(CertificationGate::class)->evaluate();
        $this->assertFalse($eval['allowed']);
        $this->assertSame('non_qa_email', $eval['reason']);
    }

    public function test_normal_provider_blocked(): void
    {
        $this->enableCertConfig();
        config(['exs_order_certification.provider' => 'kobbopay']);
        app(CertificationContext::class)->activate(
            CertificationContext::SCENARIO_SUCCESS,
            'lifecycle@qa.exswaping.com',
            str_repeat('t', 16)
        );
        $eval = app(CertificationGate::class)->evaluate();
        $this->assertFalse($eval['allowed']);
        $this->assertSame('provider_not_internal_fake', $eval['reason']);
    }

    public function test_success_destination_is_synthetic_and_unroutable(): void
    {
        $order = $this->markedTask(CertificationContext::SCENARIO_SUCCESS);
        $order->transfer_to_account = 'QA_CERT_UNROUTABLE_'.$order->public_id;
        $ctx = app(CertificationDestinationIssuer::class)->resolve($order, false);
        $this->assertSame('requisites', $ctx['mode']);
        $this->assertSame('QA_CERT_UNROUTABLE_'.$order->public_id, $ctx['account']);
        $this->assertTrue(InboundPaymentDestinationGuard::taskHasAssignedDestination($order));
        $this->assertStringStartsWith('QA_CERT_UNROUTABLE_', (string) $ctx['account']);
    }

    public function test_no_destination_fail_closed(): void
    {
        $order = $this->markedTask(CertificationContext::SCENARIO_FAIL_CLOSED);
        $order->transfer_to_account = null;
        $ctx = app(CertificationDestinationIssuer::class)->resolve($order, false);
        $this->assertSame('none', $ctx['mode']);
        $this->assertNull($ctx['account']);
        $this->assertFalse(InboundPaymentDestinationGuard::taskHasAssignedDestination($order));
        $this->assertSame(CertificationOrderMarker::PROVIDER, $order->opt_params['CERTIFICATION_PROVIDER']);
    }

    public function test_synthetic_payment_rejects_unmarked_and_destless(): void
    {
        $sim = app(CertificationPaymentSimulator::class);
        $unmarked = new Task();
        $unmarked->id = 910001;
        $unmarked->opt_params = [];
        try {
            $sim->emitPaid($unmarked);
            $this->fail('unmarked order must not accept synthetic payment');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('CERTIFICATION_REQUEST=REJECTED', $e->getMessage());
        }

        $destless = $this->markedTask(CertificationContext::SCENARIO_SUCCESS);
        $destless->transfer_to_account = null;
        try {
            $sim->emitPaid($destless);
            $this->fail('destless cert order must not accept synthetic payment');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('no destination', $e->getMessage());
        }
    }

    public function test_duplicate_payment_is_idempotent_in_transition_owner(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/Orders/Transitions/ConfirmedInboundPaidTransition.php');
        $this->assertStringContainsString("'outcome' => 'already_paid'", $src);
        $sim = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/Certification/CertificationPaymentSimulator.php');
        $this->assertStringContainsString('already_paid', $src);
        $this->assertStringContainsString('grantPaidWritePermit', $sim);
        $this->assertStringContainsString('already_completed', $sim);
        $this->assertStringNotContainsString('->success(', $sim);
    }

    public function test_paid_processing_completion_account_admin_history_wired(): void
    {
        $runner = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/Certification/CertificationLifecycleRunner.php');
        $this->assertStringContainsString("'PAID_TRANSITION'", $runner);
        $this->assertStringContainsString("'PROCESSING'", $runner);
        $this->assertStringContainsString("'COMPLETION'", $runner);
        $this->assertStringContainsString("'ACCOUNT_HISTORY'", $runner);
        $this->assertStringContainsString("'ADMIN_HISTORY'", $runner);
        $this->assertStringContainsString('WAITING_HANDLE', $runner);
        $this->assertStringContainsString('where(\'id_user\'', $runner);
        $this->assertStringContainsString('TaskStatusEnum::COMPLETED', $runner);
        $account = (string) file_get_contents(dirname(__DIR__, 3).'/packages/ExchangerClient/Http/Controller/Account/OrdersController.php');
        $this->assertStringContainsString("where('id_user'", $account);
        $admin = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/Administrator/Orders/OrdersController.php');
        $this->assertStringContainsString('class OrdersController', $admin);
        $this->assertStringContainsString('Task::', $admin);
    }

    public function test_order_created_mail_condition_and_cert_skip_smtp(): void
    {
        $this->assertFalse(OrderCreatedCondition::destinationAllowsCreatedMail(
            PaymentDestinationRouter::OWNER_KOBBOPAY,
            false,
        ));
        $this->assertTrue(OrderCreatedCondition::destinationAllowsCreatedMail(
            PaymentDestinationRouter::OWNER_KOBBOPAY,
            true,
        ));
        $manager = (string) file_get_contents(dirname(__DIR__, 3).'/packages/Order/Bindings/ManagerOrder.php');
        $this->assertStringContainsString('recordMailEligibility', $manager);
        $this->assertStringContainsString('Never enqueue SMTP for certification orders', $manager);
        $job = (string) file_get_contents(dirname(__DIR__, 3).'/packages/SmartMailer/DispatchJobs/Orders/OrderCreatedMailJob.php');
        $this->assertStringContainsString('certification_order_no_smtp', $job);
    }

    public function test_telegram_destination_gate_and_cert_sink(): void
    {
        $this->assertFalse(OrderCreatedTelegramNotifier::destinationAllowsCreatedNotification(
            PaymentDestinationRouter::OWNER_KOBBOPAY,
            false,
        ));
        $this->assertTrue(OrderCreatedTelegramNotifier::destinationAllowsCreatedNotification(
            PaymentDestinationRouter::OWNER_KOBBOPAY,
            true,
        ));
        $src = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/Orders/OrderCreatedTelegramNotifier.php');
        $this->assertStringContainsString('recordTelegram', $src);
        $this->assertStringContainsString('CertificationOrderMarker::isMarked', $src);
    }

    public function test_no_real_provider_client_instantiated(): void
    {
        $issuer = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/Certification/CertificationDestinationIssuer.php');
        $this->assertStringNotContainsString('PurchaseRequest', $issuer);
        $this->assertStringNotContainsString('Payments::forMerchant', $issuer);
        $this->assertStringNotContainsString('GatewayHttpClient', $issuer);
        $this->assertStringNotContainsString('merchant.kobbex.com', $issuer);
        $this->assertStringContainsString('INTERNAL_FAKE', $issuer);
        $this->assertStringContainsString('QA_CERT_', $issuer);
        $this->assertStringContainsString('QA_CERT_FAIL_', $issuer);
        $this->assertStringContainsString('destination_unavailable', $issuer);

        $merchant = (string) file_get_contents(dirname(__DIR__, 3).'/packages/Order/Invoices/Concerns/HasMerchant.php');
        $this->assertStringContainsString('certification_merchant_purchase_blocked', $merchant);

        $http = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/Certification/CertificationForbiddenKobbopayHttpClient.php');
        $this->assertStringContainsString('certification session forbids gateway HTTP', $http);

        $inner = $this->createMock(GatewayHttpClientInterface::class);
        $inner->expects($this->never())->method('send');
        $context = new CertificationContext();
        $context->activate(
            CertificationContext::SCENARIO_SUCCESS,
            'lifecycle@qa.exswaping.com',
            str_repeat('t', 16)
        );
        $client = new CertificationForbiddenKobbopayHttpClient($inner, $context);
        try {
            $client->send(
                ['baseUrl' => 'https://merchant.kobbex.com'],
                'POST',
                '/api/crypto/invoice/create',
                ['amount' => 1],
                new GatewayLogContext('kobbopay', 'in', 'purchase')
            );
            $this->fail('certification HTTP wrapper must throw');
        } catch (CertificationKobbopayForbiddenException $e) {
            $this->assertStringContainsString('REAL_KOBBOPAY_CALL_POSSIBLE=NO', $e->getMessage());
        }
        $this->assertSame(1, $context->kobbopayHttpCallCount());
    }

    public function test_inactive_wrapper_does_not_count_as_kobbopay_call(): void
    {
        $inner = $this->createMock(GatewayHttpClientInterface::class);
        $inner->expects($this->once())->method('send')->willReturn(['ok' => true]);
        $context = new CertificationContext();
        $client = new CertificationForbiddenKobbopayHttpClient($inner, $context);
        $out = $client->send(
            ['baseUrl' => 'https://merchant.kobbex.com'],
            'POST',
            '/api/crypto/invoice/create',
            [],
            new GatewayLogContext('kobbopay', 'in', 'purchase')
        );
        $this->assertSame(['ok' => true], $out);
        $this->assertSame(0, $context->kobbopayHttpCallCount());
    }

    public function test_public_http_create_cannot_enable_certification(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/packages/ExchangerClient/Http/Controller/Operations/OrderPayController.php');
        $this->assertStringContainsString('requestClaimsCertification', $controller);
        $this->assertStringContainsString('CERTIFICATION_REQUEST_REJECTED', $controller);
        $this->assertStringContainsString('http_create_forbidden', $controller);
        $this->assertStringContainsString('403', $controller);
        $cfg = (string) file_get_contents(dirname(__DIR__, 3).'/config/exs_order_certification.php');
        $this->assertStringContainsString("env('EXS_ORDER_CERTIFICATION_ENABLED', false)", $cfg);
    }

    public function test_poll_payout_webhook_recovery_skip_cert_orders(): void
    {
        $poll = (string) file_get_contents(dirname(__DIR__, 3).'/packages/Transaction/Concerns/SupportsCheckPayment.php');
        $this->assertStringContainsString('CERTIFICATION_ORDER: provider polling disabled', $poll);
        $queue = (string) file_get_contents(dirname(__DIR__, 3).'/app/Console/Commands/MerchantAutopayQueueCommand.php');
        $this->assertStringContainsString('autopay_queue_skipped_certification_order', $queue);
        $run = (string) file_get_contents(dirname(__DIR__, 3).'/app/Console/Commands/MerchantAutopayRunCommand.php');
        $this->assertStringContainsString('autopay_run_skipped_certification_order', $run);
        $hook = (string) file_get_contents(dirname(__DIR__, 3).'/app/Gateways/Crypto/Kobbopay/Services/KobbopayInboundWebhookService.php');
        $this->assertStringContainsString('CertificationOrderMarker::isMarked($task)', $hook);
        $recovery = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/Payments/CheckoutInvoiceRecoveryService.php');
        $this->assertStringContainsString('CertificationOrderMarker::isMarked($order)', $recovery);
    }

    public function test_marker_payload_is_unmistakable(): void
    {
        $payload = CertificationOrderMarker::payload(CertificationContext::SCENARIO_SUCCESS);
        $this->assertSame('YES', $payload['QA_CERTIFICATION']);
        $this->assertSame('YES', $payload['NO_REAL_FUNDS']);
        $this->assertSame('INTERNAL_FAKE', $payload['CERTIFICATION_PROVIDER']);
        $task = new Task();
        $task->opt_params = $payload;
        $this->assertTrue(CertificationOrderMarker::isMarked($task));
        $unmarked = new Task();
        $unmarked->opt_params = [];
        $this->assertFalse(CertificationOrderMarker::isMarked($unmarked));
    }

    public function test_provider_is_registered_and_command_is_artisan_only(): void
    {
        $providers = (string) file_get_contents(dirname(__DIR__, 3).'/bootstrap/providers.php');
        $this->assertStringContainsString('OrderCertificationServiceProvider', $providers);
        $cmd = (string) file_get_contents(dirname(__DIR__, 3).'/app/Console/Commands/OrdersCertificationLifecycleCommand.php');
        $this->assertStringContainsString('orders:certification-lifecycle', $cmd);
        $this->assertStringContainsString('flag_disabled', $cmd);
        $this->assertSame(
            app(\App\Services\Certification\CertificationForbiddenKobbopayHttpClient::class)::class,
            app(GatewayHttpClientInterface::class)::class
        );
    }

    private function enableCertConfig(): void
    {
        config([
            'exs_order_certification.enabled' => true,
            'exs_order_certification.token' => str_repeat('t', 16),
            'exs_order_certification.provider' => 'INTERNAL_FAKE',
            'exs_order_certification.request_header' => 'X-Exs-Certification-Request',
            'exs_order_certification.token_header' => 'X-Exs-Certification-Token',
            'exs_order_certification.email_suffix' => '@qa.exswaping.com',
        ]);
    }

    private function certRequest(string $email, string $token): Request
    {
        $request = Request::create('/client-api/v1/order', 'POST', ['email' => $email]);
        $request->headers->set('X-Exs-Certification-Request', '1');
        $request->headers->set('X-Exs-Certification-Token', $token);

        return $request;
    }

    private function markedTask(string $scenario): Task
    {
        $task = new Task();
        $task->id = 910100;
        $task->public_id = '1786990000100';
        $task->opt_params = CertificationOrderMarker::payload($scenario);

        return $task;
    }
}
