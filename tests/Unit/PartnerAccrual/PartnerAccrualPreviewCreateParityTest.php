<?php

declare(strict_types=1);

namespace Tests\Unit\PartnerAccrual;

use App\Models\PartnerAccrualInstallment;
use App\Models\PartnerAccrualPlan;
use App\Models\User;
use App\Services\PartnerAccrual\AccrualMoney;
use App\Services\PartnerAccrual\Exceptions\InvalidScheduleSeed;
use App\Services\PartnerAccrual\Exceptions\OverlappingPlanExists;
use App\Services\PartnerAccrual\PartnerAccrualPlanGenerator;
use App\Services\PartnerAccrual\PartnerAccrualPlanLifecycleService;
use App\Services\PartnerAccrual\PartnerAccrualPlanPreviewService;
use App\Services\PartnerAccrual\PartnerAccrualScheduleSeedBinder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PartnerAccrualPreviewCreateParityTest extends TestCase
{
    private PartnerAccrualPlanGenerator $generator;

    private PartnerAccrualPlanPreviewService $preview;

    private PartnerAccrualPlanLifecycleService $lifecycle;

    private PartnerAccrualScheduleSeedBinder $binder;

    protected function setUp(): void
    {
        parent::setUp();
        PartnerAccrualSqliteHarness::bootIsolated();
        // Stable app key for HMAC in tests.
        Config::set('app.key', 'base64:'.base64_encode(str_repeat('A', 32)));
        Config::set('partner_accrual.allow_unbound_schedule_seed', false);
        $this->generator = new PartnerAccrualPlanGenerator();
        $this->binder = new PartnerAccrualScheduleSeedBinder();
        $this->preview = new PartnerAccrualPlanPreviewService($this->generator, $this->binder);
        $this->lifecycle = new PartnerAccrualPlanLifecycleService($this->generator, seedBinder: $this->binder);
    }

    private function user(): User
    {
        $id = DB::table('users')->insertGetId([
            'email' => 'parity-'.uniqid('', true).'@example.test',
            'name' => 'Parity',
            'banned_at' => null,
            'deactivation' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }

    public function test_same_preview_seed_persists_exact_schedule(): void
    {
        $user = $this->user();
        $input = [
            'user_id' => (int) $user->id,
            'total_amount' => '100.00',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-10',
            'currency_code' => 'USD',
        ];
        $p = $this->preview->preview($input);
        $this->assertNotSame('', $p['schedule_seed']);
        $this->assertSame('100.00000000', $p['sum']);

        $plan = $this->lifecycle->create([
            ...$input,
            'currency_code_id' => 8,
            'schedule_seed' => $p['schedule_seed'],
            'created_by_admin_id' => 1,
        ], activate: true);

        $rows = PartnerAccrualInstallment::query()
            ->where('plan_id', $plan->id)
            ->orderBy('sequence')
            ->get(['sequence', 'scheduled_for', 'amount']);

        $this->assertSame(count($p['schedule']), $rows->count());
        foreach ($p['schedule'] as $i => $row) {
            $persisted = $rows[$i];
            $this->assertSame($row['sequence'], (int) $persisted->sequence);
            $this->assertSame($row['scheduled_for'], (string) $persisted->scheduled_for->format('Y-m-d'));
            $this->assertSame(
                AccrualMoney::scale8(AccrualMoney::of((string) $row['amount'])),
                AccrualMoney::scale8(AccrualMoney::of((string) $persisted->amount))
            );
        }
        $this->assertSame($p['rng_nonce'], (string) $plan->schedule_seed);
    }

    public function test_different_seed_can_differ_for_same_total(): void
    {
        $user = $this->user();
        $base = [
            'user_id' => (int) $user->id,
            'total_amount' => '100.00',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-10',
            'currency_code' => 'USD',
        ];
        $a = $this->preview->preview($base);
        // Force a different nonce via binder issue then preview with mismatched path:
        $tokenB = $this->binder->issue([
            ...$base,
            'rng_nonce' => bin2hex(random_bytes(16)),
        ]);
        $b = $this->preview->preview([...$base, 'schedule_seed' => $tokenB]);
        $this->assertNotSame($a['rng_nonce'], $b['rng_nonce']);
        // Amounts may or may not differ; at least generation identities differ.
        $this->assertNotSame($a['schedule_seed'], $b['schedule_seed']);
    }

    public function test_tampered_seed_rejected(): void
    {
        $user = $this->user();
        $input = [
            'user_id' => (int) $user->id,
            'total_amount' => '50.00',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-05',
            'currency_code' => 'USD',
        ];
        $p = $this->preview->preview($input);
        $tampered = substr($p['schedule_seed'], 0, -2).'zz';
        $this->expectException(InvalidScheduleSeed::class);
        $this->lifecycle->create([
            ...$input,
            'currency_code_id' => 8,
            'schedule_seed' => $tampered,
        ], activate: true);
    }

    public function test_changed_total_after_preview_rejected(): void
    {
        $user = $this->user();
        $p = $this->preview->preview([
            'user_id' => (int) $user->id,
            'total_amount' => '50.00',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-05',
            'currency_code' => 'USD',
        ]);
        $this->expectException(InvalidScheduleSeed::class);
        $this->lifecycle->create([
            'user_id' => (int) $user->id,
            'total_amount' => '60.00',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-05',
            'currency_code' => 'USD',
            'currency_code_id' => 8,
            'schedule_seed' => $p['schedule_seed'],
        ], activate: true);
    }

    public function test_changed_period_after_preview_rejected(): void
    {
        $user = $this->user();
        $p = $this->preview->preview([
            'user_id' => (int) $user->id,
            'total_amount' => '50.00',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-05',
            'currency_code' => 'USD',
        ]);
        $this->expectException(InvalidScheduleSeed::class);
        $this->lifecycle->create([
            'user_id' => (int) $user->id,
            'total_amount' => '50.00',
            'start_date' => '2026-12-02',
            'end_date' => '2026-12-06',
            'currency_code' => 'USD',
            'currency_code_id' => 8,
            'schedule_seed' => $p['schedule_seed'],
        ], activate: true);
    }

    public function test_changed_user_after_preview_rejected(): void
    {
        $u1 = $this->user();
        $u2 = $this->user();
        $p = $this->preview->preview([
            'user_id' => (int) $u1->id,
            'total_amount' => '50.00',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-05',
            'currency_code' => 'USD',
        ]);
        $this->expectException(InvalidScheduleSeed::class);
        $this->lifecycle->create([
            'user_id' => (int) $u2->id,
            'total_amount' => '50.00',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-05',
            'currency_code' => 'USD',
            'currency_code_id' => 8,
            'schedule_seed' => $p['schedule_seed'],
        ], activate: true);
    }

    public function test_missing_seed_rejected(): void
    {
        $user = $this->user();
        $this->expectException(InvalidScheduleSeed::class);
        $this->lifecycle->create([
            'user_id' => (int) $user->id,
            'total_amount' => '50.00',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-05',
            'currency_code' => 'USD',
            'currency_code_id' => 8,
        ], activate: true);
    }

    public function test_double_submit_no_duplicate_plan(): void
    {
        $user = $this->user();
        $input = [
            'user_id' => (int) $user->id,
            'total_amount' => '40.00',
            'start_date' => '2027-01-01',
            'end_date' => '2027-01-05',
            'currency_code' => 'USD',
            'currency_code_id' => 8,
        ];
        $p = $this->preview->preview($input);
        $this->lifecycle->create([...$input, 'schedule_seed' => $p['schedule_seed']], activate: true);
        $this->expectException(OverlappingPlanExists::class);
        $this->lifecycle->create([...$input, 'schedule_seed' => $p['schedule_seed']], activate: true);
        $this->assertSame(1, PartnerAccrualPlan::query()->where('user_id', $user->id)->count());
    }
}
