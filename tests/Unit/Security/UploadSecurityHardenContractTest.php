<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;

/**
 * Static contract: public upload XSS vectors stay closed.
 */
final class UploadSecurityHardenContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_admin_uploads_route_requires_admin_auth_middleware(): void
    {
        $routes = file_get_contents($this->root().'/routes/admin-frontend.php');
        $this->assertNotFalse($routes);

        // Must not register uploads before the admin.auth group.
        $unauthBlock = substr($routes, 0, (int) strpos($routes, "middleware(['2fa', 'is_reading_mode', 'admin.auth', 'admin'])"));
        $this->assertStringNotContainsString(
            "Route::post('/uploads'",
            $unauthBlock,
            'uploads must not be outside admin.auth',
        );

        $this->assertMatchesRegularExpression(
            "/admin\.auth',\s*'admin'\]\)->group\(function\s*\(\)\s*\{[\s\S]{0,800}Route::post\('\/uploads'/",
            $routes,
        );
    }

    public function test_upload_controller_bans_svg_and_client_mime(): void
    {
        $src = file_get_contents($this->root().'/app/Http/Controllers/Administrator/UploadController.php');
        $this->assertNotFalse($src);
        $this->assertStringNotContainsString('image/svg+xml', $src);
        $this->assertStringNotContainsString('getClientMimeType', $src);
        $this->assertStringContainsString('getMimeType()', $src);
        $this->assertStringContainsString("ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'webp', 'pdf']", $src);
        $this->assertMatchesRegularExpression("/BLOCKED_EXT = \[[\s\S]*'svg'/", $src);
    }

    public function test_order_chat_bans_svg(): void
    {
        $ctrl = file_get_contents($this->root().'/packages/OrderChat/Http/Controllers/Client/OrderChatController.php');
        $store = file_get_contents($this->root().'/packages/OrderChat/Support/OrderChatFileStorage.php');
        $this->assertStringNotContainsString('image/svg+xml', $ctrl);
        $this->assertStringNotContainsString('getClientOriginalExtension', $store);
        $this->assertStringContainsString('getMimeType()', $store);
    }

    public function test_attach_file_uses_possession_gate(): void
    {
        $src = file_get_contents(
            $this->root().'/packages/ExchangerClient/Http/Controller/SendImage/ImageAttachCheckController.php'
        );
        $this->assertStringContainsString('AssertOrderVerificationAccess::allows', $src);
        $this->assertStringNotContainsString('exists:tasks,public_id', $src);
        $this->assertStringNotContainsString('getClientOriginalExtension()', $src);
    }

    public function test_private_image_handler_allowlists_paths(): void
    {
        $src = file_get_contents($this->root().'/app/Http/Controllers/Administrator/HomeController.php');
        $this->assertStringContainsString('user_verification', $src);
        $this->assertStringContainsString('order_chat', $src);
        $this->assertStringContainsString('Path is not allowed', $src);
        $this->assertStringContainsString('X-Content-Type-Options', $src);
    }

    public function test_order_chat_stores_on_private_disk(): void
    {
        $store = file_get_contents($this->root().'/packages/OrderChat/Support/OrderChatFileStorage.php');
        $routes = file_get_contents($this->root().'/packages/ExchangerClient/routes/client-api.php');
        $this->assertStringContainsString("DISK_FOLDER = 'order_chat'", $store);
        $this->assertStringContainsString("disk('iexexchanger-disk')", $store);
        $this->assertStringNotContainsString('public_path(', $store);
        $this->assertStringContainsString("chat/files/{filename}", $routes);
    }

    public function test_didit_simple_signature_enforces_timestamp_skew(): void
    {
        $src = file_get_contents($this->root().'/packages/KYCPlugin/Drivers/Didit/DiditWebhookSignature.php');
        $this->assertStringContainsString('timestampWithinSkew', $src);
        $this->assertStringContainsString('300', $src);
    }
}
