<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use App\Notifications\SetPasswordNotification;
use Tests\TestCase;

final class SetPasswordNotificationTest extends TestCase
{
    public function test_setup_url_uses_reset_credentials_route(): void
    {
        config(['app.frontend_url' => 'https://exswaping.com']);

        $user = new User();
        $user->email = 'lifecycle-test@example.com';
        $user->language = 'en';

        $mail = (new SetPasswordNotification('plain-token-fixture'))->toMail($user);

        self::assertSame(
            'https://exswaping.com/auth/reset-credentials/plain-token-fixture/?email=lifecycle-test%40example.com',
            $mail->actionUrl,
        );
    }
}
