<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave B: additive schedule-generator columns + plan action audit events.
 * Source-certified only; not applied to production in Wave B.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('partner_accrual_plans')) {
            Schema::table('partner_accrual_plans', function (Blueprint $table) {
                if (! Schema::hasColumn('partner_accrual_plans', 'schedule_seed')) {
                    $table->string('schedule_seed', 64)->nullable()->after('version');
                }
                if (! Schema::hasColumn('partner_accrual_plans', 'min_installment')) {
                    $table->decimal('min_installment', 20, 8)->nullable()->after('schedule_seed');
                }
                if (! Schema::hasColumn('partner_accrual_plans', 'max_installment')) {
                    $table->decimal('max_installment', 20, 8)->nullable()->after('min_installment');
                }
                if (! Schema::hasColumn('partner_accrual_plans', 'variance_percent')) {
                    $table->unsignedSmallInteger('variance_percent')->nullable()->after('max_installment');
                }
                if (! Schema::hasColumn('partner_accrual_plans', 'activated_at')) {
                    $table->timestamp('activated_at')->nullable()->after('variance_percent');
                }
            });
        }

        if (! Schema::hasTable('partner_accrual_plan_events')) {
            Schema::create('partner_accrual_plan_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('plan_id')->index();
                $table->string('event_type', 64)->index();
                $table->unsignedBigInteger('actor_admin_id')->nullable()->index();
                $table->json('payload')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_accrual_plan_events');

        if (Schema::hasTable('partner_accrual_plans')) {
            Schema::table('partner_accrual_plans', function (Blueprint $table) {
                foreach (['activated_at', 'variance_percent', 'max_installment', 'min_installment', 'schedule_seed'] as $col) {
                    if (Schema::hasColumn('partner_accrual_plans', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
