<?php

declare(strict_types=1);

namespace Tests\Unit\Gateways\Kobbopay;

use App\Enums\TaskStatusEnum;
use App\Gateways\Crypto\Kobbopay\Services\KobbopayInboundWebhookService;
use App\Gateways\Crypto\Kobbopay\Services\KobbopayWebhookSecretResolver;
use App\Gateways\Crypto\Kobbopay\Services\KobbopayWebhookSignatureVerifier;
use App\Models\GatewayMerchant;
use App\Models\MerchantTransactionData;
use App\Models\ProviderWebhookEvent;
use App\Models\Task;
use iEXPackages\Payments\Logging\Services\MerchantFlowLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Integration-style unit tests against the real DB for idempotency/correlation paths.
 * Confirmation mutation via checkInPayment is mocked at TransactionFacade where needed.
 */
final class KobbopayWebhookIdempotencyAndMappingTest extends TestCase
{
    private string $secret = 'unit-test-kobbopay-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasTable('provider_webhook_events')) {
            $this->markTestSkipped('provider_webhook_events migration not applied');
        }

        putenv('KOBBOPAY_WEBHOOK_SECRET=' . $this->secret);
        $_ENV['KOBBOPAY_WEBHOOK_SECRET'] = $this->secret;
        $_SERVER['KOBBOPAY_WEBHOOK_SECRET'] = $this->secret;
    }

    public function test_duplicate_event_is_acknowledged_without_second_row(): void
    {
        $service = $this->makeService();
        $paymentId = 'kobb-unit-' . uniqid();
        $body = json_encode([
            'event' => 'transaction.created',
            'paymentId' => $paymentId,
            'orderId' => '0',
        ], JSON_UNESCAPED_SLASHES);

        $req1 = $this->signedRequest($body, 'payment:' . $paymentId . ':transaction.created');
        $res1 = $this->dispatch($service, $req1);
        $this->assertSame(200, $res1->getStatusCode());
        $json1 = json_decode($res1->getContent(), true);
        $this->assertTrue($json1['accepted'] ?? false);

        $count = ProviderWebhookEvent::query()
            ->where('provider', 'kobbopay')
            ->where('event_id', 'payment:' . $paymentId . ':transaction.created')
            ->count();
        $this->assertSame(1, $count);

        $req2 = $this->signedRequest($body, 'payment:' . $paymentId . ':transaction.created');
        $res2 = $this->dispatch($service, $req2);
        $this->assertSame(200, $res2->getStatusCode());
        $json2 = json_decode($res2->getContent(), true);
        $this->assertTrue($json2['accepted'] ?? false);
        $this->assertTrue($json2['duplicate'] ?? false);

        $count2 = ProviderWebhookEvent::query()
            ->where('provider', 'kobbopay')
            ->where('event_id', 'payment:' . $paymentId . ':transaction.created')
            ->count();
        $this->assertSame(1, $count2);
    }

    public function test_same_event_id_different_payload_conflicts(): void
    {
        $service = $this->makeService();
        $eventId = 'payment:conflict-' . uniqid() . ':payment.confirmed';
        $body1 = '{"event":"payment.confirmed","paymentId":"a"}';
        $body2 = '{"event":"payment.confirmed","paymentId":"b"}';

        $res1 = $this->dispatch($service, $this->signedRequest($body1, $eventId));
        $this->assertSame(200, $res1->getStatusCode());

        $res2 = $this->dispatch($service, $this->signedRequest($body2, $eventId));
        $this->assertSame(409, $res2->getStatusCode());
        $json = json_decode($res2->getContent(), true);
        $this->assertSame('event_payload_conflict', $json['error'] ?? null);
    }

    public function test_invalid_signature_returns_json_401(): void
    {
        $service = $this->makeService();
        $request = Request::create(
            '/callbacks/v1/webhook/kobbopay',
            'POST',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_KOBBOPAY_EVENT_ID' => 'evt-bad',
                'HTTP_X_KOBBOPAY_TIMESTAMP' => (string) time(),
                'HTTP_X_KOBBOPAY_SIGNATURE' => str_repeat('0', 64),
            ],
            '{"event":"payment.confirmed"}'
        );

        $res = $this->dispatch($service, $request);
        $this->assertSame(401, $res->getStatusCode());
        $this->assertStringContainsString('application/json', (string) $res->headers->get('Content-Type'));
        $json = json_decode($res->getContent(), true);
        $this->assertFalse($json['accepted'] ?? true);
        $this->assertSame('signature_invalid', $json['error'] ?? null);
    }

    public function test_tracker_correlation_and_unknown_event_no_status_change(): void
    {
        $mtd = MerchantTransactionData::query()
            ->where('service_name', 'kobbopay')
            ->whereNotNull('id_from_merchant')
            ->where('id_from_merchant', '!=', '')
            ->latest('id')
            ->first();

        if (!$mtd || !$mtd->tasks) {
            $this->markTestSkipped('No kobbopay MerchantTransactionData available');
        }

        $task = $mtd->tasks;
        $before = (int) $task->status;
        $paymentId = (string) $mtd->id_from_merchant;
        $eventId = 'payment:' . $paymentId . ':custom.unknown.' . uniqid();
        $body = json_encode([
            'event' => 'custom.unknown',
            'paymentId' => $paymentId,
            'tracker_id' => $paymentId,
        ], JSON_UNESCAPED_SLASHES);

        $service = $this->makeService();
        $res = $this->dispatch($service, $this->signedRequest($body, $eventId));
        $this->assertSame(200, $res->getStatusCode());

        $task->refresh();
        $this->assertSame($before, (int) $task->status);
    }

    public function test_expired_does_not_downgrade_completed_task(): void
    {
        $completed = Task::query()
            ->where('status', TaskStatusEnum::COMPLETED->value)
            ->where('merchant_provider', 'kobbopay')
            ->latest('id')
            ->first();

        if (!$completed) {
            $this->markTestSkipped('No completed kobbopay task for expire downgrade guard');
        }

        $mtd = MerchantTransactionData::query()
            ->where('id_task', $completed->id)
            ->where('service_name', 'kobbopay')
            ->first();

        if (!$mtd || !$mtd->id_from_merchant) {
            $this->markTestSkipped('Completed task missing kobbopay mtd');
        }

        $paymentId = (string) $mtd->id_from_merchant;
        $eventId = 'payment:' . $paymentId . ':payment.expired.' . uniqid();
        $body = json_encode([
            'event' => 'payment.expired',
            'paymentId' => $paymentId,
        ], JSON_UNESCAPED_SLASHES);

        $service = $this->makeService();
        $res = $this->dispatch($service, $this->signedRequest($body, $eventId));
        $this->assertSame(200, $res->getStatusCode());

        $completed->refresh();
        $this->assertSame(TaskStatusEnum::COMPLETED->value, (int) $completed->status);
    }

    public function test_response_is_json_not_html(): void
    {
        $service = $this->makeService();
        $body = '{"event":"transaction.created","paymentId":"json-check-' . uniqid() . '"}';
        $res = $this->dispatch(
            $service,
            $this->signedRequest($body, 'payment:json-check:transaction.created')
        );

        $this->assertStringContainsString('application/json', (string) $res->headers->get('Content-Type'));
        $this->assertStringNotContainsString('<html', strtolower($res->getContent()));
    }

    private function makeService(): KobbopayInboundWebhookService
    {
        $tolerance = (int) env('KOBBOPAY_WEBHOOK_TOLERANCE_SECONDS', 300);

        return new KobbopayInboundWebhookService(
            verifier: new KobbopayWebhookSignatureVerifier($tolerance > 0 ? $tolerance : 300),
            secretResolver: new KobbopayWebhookSecretResolver($this->secret),
            flowLogger: app(MerchantFlowLogger::class),
        );
    }

    private function signedRequest(string $body, string $eventId): Request
    {
        $verifier = new KobbopayWebhookSignatureVerifier(300);
        $ts = (string) time();
        $sig = $verifier->sign($this->secret, $ts, $body);

        return Request::create(
            '/callbacks/v1/webhook/kobbopay',
            'POST',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_KOBBOPAY_EVENT_ID' => $eventId,
                'HTTP_X_KOBBOPAY_TIMESTAMP' => $ts,
                'HTTP_X_KOBBOPAY_SIGNATURE' => $sig,
                'HTTP_X_KOBBOPAY_DELIVERY_ID' => 'd-' . substr(md5($eventId), 0, 8),
            ],
            $body
        );
    }

    private function dispatch(KobbopayInboundWebhookService $service, Request $request)
    {
        $merchant = GatewayMerchant::query()->where('alias', 'kobbopay')->first();
        $hash = $merchant ? trim((string) ($merchant->security_hash ?? '')) : '';

        return $service->handleHttp($request, $hash);
    }
}
