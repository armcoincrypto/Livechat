<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Models\User;
use App\Services\Auth\UserAccountLifecycleService;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Lifecycle policy tests for unverified auto-deactivation and password-reset recovery.
 */
final class UserAccountLifecycleServiceTest extends TestCase
{
    public function test_funded_or_completed_status_constants_include_paid_and_completed(): void
    {
        $statuses = UserAccountLifecycleService::FUNDED_OR_COMPLETED_TASK_STATUSES;
        self::assertContains(3, $statuses);
        self::assertContains(4, $statuses);
        self::assertContains(7, $statuses);
    }

    public function test_has_meaningful_history_true_when_num_auth_positive(): void
    {
        $service = new UserAccountLifecycleService();
        $user = new User();
        $user->num_auth = 2;
        $user->created_at = Carbon::parse('2026-01-01');
        $user->last_login_at = Carbon::parse('2026-01-01');

        // Avoid DB: stub tasks relation via anonymous subclass
        $user = new class extends User {
            public function tasks()
            {
                return new class {
                    public function exists(): bool
                    {
                        return false;
                    }
                };
            }
        };
        $user->num_auth = 5;
        $user->created_at = Carbon::parse('2026-01-01');
        $user->last_login_at = Carbon::parse('2026-01-01');

        self::assertTrue($service->hasMeaningfulCustomerHistory($user));
    }

    public function test_has_meaningful_history_true_when_login_after_registration(): void
    {
        $service = new UserAccountLifecycleService();
        $user = new class extends User {
            public function tasks()
            {
                return new class {
                    public function exists(): bool
                    {
                        return false;
                    }
                };
            }
        };
        $user->num_auth = 0;
        $user->created_at = Carbon::parse('2026-01-01 10:00:00');
        $user->last_login_at = Carbon::parse('2026-01-02 10:00:00');

        self::assertTrue($service->hasMeaningfulCustomerHistory($user));
    }

    public function test_has_meaningful_history_true_when_tasks_exist(): void
    {
        $service = new UserAccountLifecycleService();
        $user = new class extends User {
            public function tasks()
            {
                return new class {
                    public function exists(): bool
                    {
                        return true;
                    }
                };
            }
        };
        $user->num_auth = 0;
        $user->created_at = Carbon::parse('2026-01-01');
        $user->last_login_at = Carbon::parse('2026-01-01');

        self::assertTrue($service->hasMeaningfulCustomerHistory($user));
    }

    public function test_recover_after_password_reset_marks_verified_and_clears_deactivation(): void
    {
        $service = new UserAccountLifecycleService();
        $user = new class extends User {
            public bool $saved = false;

            public function hasVerifiedEmail(): bool
            {
                return $this->email_verified_at !== null;
            }

            public function markEmailAsVerified()
            {
                $this->email_verified_at = Carbon::now();
                return true;
            }

            public function save(array $options = [])
            {
                $this->saved = true;
                return true;
            }
        };
        $user->email_verified_at = null;
        $user->deactivation = 1;

        $service->recoverAfterSuccessfulPasswordReset($user);

        self::assertNotNull($user->email_verified_at);
        self::assertSame(0, (int) $user->deactivation);
        self::assertTrue($user->saved);
    }

    public function test_recover_does_not_touch_kyc_fields(): void
    {
        $service = new UserAccountLifecycleService();
        $user = new class extends User {
            public function hasVerifiedEmail(): bool
            {
                return true;
            }

            public function markEmailAsVerified()
            {
                self::fail('should not re-verify already verified email');
            }

            public function save(array $options = [])
            {
                return true;
            }
        };
        $user->email_verified_at = Carbon::now();
        $user->deactivation = 0;
        $user->is_verification = 0;
        $user->is_verify_account = 0;

        $service->recoverAfterSuccessfulPasswordReset($user);

        self::assertSame(0, (int) $user->is_verification);
        self::assertSame(0, (int) $user->is_verify_account);
    }

    public function test_login_controller_emits_stable_deactivation_and_banned_codes(): void
    {
        $loginPath = base_path('packages/ExchangerClient/Http/Controller/Auth/LoginController.php');
        $src = file_get_contents($loginPath);
        self::assertIsString($src);
        self::assertStringContainsString("'code' => 'ACCOUNT_DEACTIVATED'", $src);
        self::assertStringContainsString("'code' => 'ACCOUNT_BANNED'", $src);
    }

    public function test_monitoring_daily_uses_lifecycle_scope(): void
    {
        $src = file_get_contents(base_path('app/Console/Commands/MonitoringDailyCommand.php'));
        self::assertIsString($src);
        self::assertStringContainsString('scopeEligibleForUnverifiedAutoDeactivation', $src);
        self::assertStringContainsString('whereDoesntHave(\'tasks\')', $src);
        self::assertStringContainsString('--dry-run', $src);
    }

    public function test_reset_password_controller_calls_lifecycle_recovery(): void
    {
        $src = file_get_contents(base_path('app/Http/Controllers/Auth/ResetPasswordController.php'));
        self::assertIsString($src);
        self::assertStringContainsString('recoverAfterSuccessfulPasswordReset', $src);
        self::assertStringContainsString('UserAccountLifecycleService', $src);
    }
}
