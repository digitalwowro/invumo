<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestampTz('suspended_at')->nullable();
            $table->timestampTz('last_login_at')->nullable();

            $table->index(['created_at', 'id'], 'users_created_at_id_index');
        });

        DB::statement(<<<'SQL'
            CREATE INDEX users_suspended_at_id_index
            ON users (suspended_at, id)
            WHERE suspended_at IS NOT NULL
            SQL);

        Schema::table('accounts', function (Blueprint $table) {
            $table->text('plan_status')->default('ACTIVE');
            $table->timestampTz('plan_started_at')->nullable();
            $table->timestampTz('trial_ends_at')->nullable();
            $table->timestampTz('access_ends_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestampTz('ended_at')->nullable();
            $table->timestampTz('suspended_at')->nullable();

            $table->index(
                ['plan_status', 'access_ends_at', 'id'],
                'accounts_plan_status_access_ends_at_id_index',
            );
            $table->index(['created_at', 'id'], 'accounts_created_at_id_index');
        });

        DB::statement('UPDATE accounts SET plan_started_at = created_at');
        DB::statement(<<<'SQL'
            ALTER TABLE accounts
            ALTER COLUMN plan_started_at SET NOT NULL,
            ALTER COLUMN plan_started_at SET DEFAULT CURRENT_TIMESTAMP
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE accounts
            ADD CONSTRAINT accounts_plan_status_check
            CHECK (plan_status IN ('TRIALING', 'ACTIVE', 'PAST_DUE', 'CANCELED', 'EXPIRED')),
            ADD CONSTRAINT accounts_trial_ends_after_start_check
            CHECK (trial_ends_at IS NULL OR trial_ends_at >= plan_started_at),
            ADD CONSTRAINT accounts_access_ends_after_start_check
            CHECK (access_ends_at IS NULL OR access_ends_at >= plan_started_at),
            ADD CONSTRAINT accounts_cancel_at_period_end_check
            CHECK (NOT cancel_at_period_end OR access_ends_at IS NOT NULL),
            ADD CONSTRAINT accounts_ended_status_check
            CHECK (
                (plan_status IN ('CANCELED', 'EXPIRED') AND ended_at IS NOT NULL)
                OR
                (plan_status NOT IN ('CANCELED', 'EXPIRED') AND ended_at IS NULL)
            ),
            ADD CONSTRAINT accounts_trialing_end_check
            CHECK (plan_status <> 'TRIALING' OR trial_ends_at IS NOT NULL)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX accounts_suspended_at_id_index
            ON accounts (suspended_at, id)
            WHERE suspended_at IS NOT NULL
            SQL);

        Schema::table('companies', function (Blueprint $table) {
            $table->index(['created_at', 'id'], 'companies_created_at_id_index');
        });

        Schema::table('audit_events', function (Blueprint $table) {
            $table->foreignUuid('impersonator_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->index('impersonator_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('audit_events', function (Blueprint $table) {
            $table->dropForeign(['impersonator_user_id']);
            $table->dropColumn('impersonator_user_id');
        });

        DB::statement('DROP INDEX IF EXISTS accounts_suspended_at_id_index');
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex('companies_created_at_id_index');
        });
        DB::statement(<<<'SQL'
            ALTER TABLE accounts
            DROP CONSTRAINT IF EXISTS accounts_plan_status_check,
            DROP CONSTRAINT IF EXISTS accounts_trial_ends_after_start_check,
            DROP CONSTRAINT IF EXISTS accounts_access_ends_after_start_check,
            DROP CONSTRAINT IF EXISTS accounts_cancel_at_period_end_check,
            DROP CONSTRAINT IF EXISTS accounts_ended_status_check,
            DROP CONSTRAINT IF EXISTS accounts_trialing_end_check
            SQL);
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropIndex('accounts_plan_status_access_ends_at_id_index');
            $table->dropIndex('accounts_created_at_id_index');
            $table->dropColumn([
                'plan_status',
                'plan_started_at',
                'trial_ends_at',
                'access_ends_at',
                'cancel_at_period_end',
                'ended_at',
                'suspended_at',
            ]);
        });

        DB::statement('DROP INDEX IF EXISTS users_suspended_at_id_index');
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_created_at_id_index');
            $table->dropColumn(['suspended_at', 'last_login_at']);
        });
    }
};
