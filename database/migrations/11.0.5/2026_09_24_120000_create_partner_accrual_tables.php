<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave A: additive partner monthly accrual foundation (USD v1).
 * Source-certified only; not applied to production in Wave A.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('partner_accrual_plans')) {
            Schema::create('partner_accrual_plans', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('currency_code', 16)->default('USD');
                $table->unsignedInteger('currency_code_id')->nullable()->index();
                $table->decimal('total_amount', 20, 8);
                $table->decimal('credited_amount', 20, 8)->default('0');
                $table->date('start_date');
                $table->date('end_date');
                $table->string('status', 32)->default('DRAFT')->index();
                $table->unsignedBigInteger('created_by_admin_id')->nullable()->index();
                $table->timestamp('paused_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->string('error_code', 64)->nullable();
                $table->text('error_message')->nullable();
                $table->json('metadata')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();

                $table->index(['user_id', 'status'], 'partner_accrual_plans_user_status_idx');
                $table->index(['start_date', 'end_date'], 'partner_accrual_plans_period_idx');
            });
        }

        if (! Schema::hasTable('partner_accrual_installments')) {
            Schema::create('partner_accrual_installments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('plan_id')->index();
                $table->unsignedInteger('sequence');
                $table->date('scheduled_for')->index();
                $table->decimal('amount', 20, 8);
                $table->string('status', 32)->default('PENDING')->index();
                $table->string('idempotency_key', 191);
                $table->unsignedBigInteger('balance_log_id')->nullable()->index();
                $table->timestamp('executed_at')->nullable();
                $table->unsignedInteger('attempt_count')->default(0);
                $table->text('last_error')->nullable();
                $table->timestamps();

                $table->unique(['plan_id', 'sequence'], 'partner_accrual_installments_plan_seq_unique');
                $table->unique('idempotency_key', 'partner_accrual_installments_idempotency_unique');
                $table->index(['status', 'scheduled_for'], 'partner_accrual_installments_due_idx');
            });
        }

        // Additive audit columns on existing balance log (nullable, cheap).
        if (Schema::hasTable('user_balance_log')) {
            Schema::table('user_balance_log', function (Blueprint $table) {
                if (! Schema::hasColumn('user_balance_log', 'source')) {
                    $table->string('source', 64)->nullable()->after('route_type');
                }
                if (! Schema::hasColumn('user_balance_log', 'reference')) {
                    $table->string('reference', 191)->nullable()->after('source');
                }
            });

            // Unique reference when present (MySQL/SQLite allow multiple NULLs).
            try {
                Schema::table('user_balance_log', function (Blueprint $table) {
                    $table->unique('reference', 'user_balance_log_reference_unique');
                });
            } catch (\Throwable) {
                // Index already present (re-run / partial apply).
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_balance_log')) {
            try {
                Schema::table('user_balance_log', function (Blueprint $table) {
                    $table->dropUnique('user_balance_log_reference_unique');
                });
            } catch (\Throwable) {
                // ignore
            }

            Schema::table('user_balance_log', function (Blueprint $table) {
                $cols = [];
                if (Schema::hasColumn('user_balance_log', 'reference')) {
                    $cols[] = 'reference';
                }
                if (Schema::hasColumn('user_balance_log', 'source')) {
                    $cols[] = 'source';
                }
                if ($cols !== []) {
                    $table->dropColumn($cols);
                }
            });
        }

        Schema::dropIfExists('partner_accrual_installments');
        Schema::dropIfExists('partner_accrual_plans');
    }
};
