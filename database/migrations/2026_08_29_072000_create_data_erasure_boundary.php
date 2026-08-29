<?php

use App\Foundation\Database\Schema\MigrationDatabaseRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_invitations', function (Blueprint $table): void {
            $table->text('invited_email')->nullable()->change();
            $table->text('invited_email_normalized')->nullable()->change();
            $table->timestampTz('identity_erased_at')->nullable();
        });

        DB::statement('ALTER TABLE company_invitations DROP CONSTRAINT company_invitations_normalized_email_check');
        DB::statement('ALTER TABLE company_invitations DROP CONSTRAINT company_invitations_acceptance_check');
        DB::statement(<<<'SQL'
            ALTER TABLE company_invitations
            ADD CONSTRAINT company_invitations_identity_erasure_check CHECK (
                    (identity_erased_at IS NULL
                        AND invited_email IS NOT NULL
                        AND invited_email_normalized IS NOT NULL
                        AND invited_email_normalized = lower(invited_email_normalized))
                    OR
                    (identity_erased_at IS NOT NULL
                        AND invited_email IS NULL
                        AND invited_email_normalized IS NULL)
                ),
            ADD CONSTRAINT company_invitations_acceptance_check CHECK (
                    (accepted_at IS NULL AND accepted_by_user_id IS NULL)
                    OR
                    (accepted_at IS NOT NULL AND (
                        accepted_by_user_id IS NOT NULL OR identity_erased_at IS NOT NULL
                    ))
                )
            SQL);
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION invumo_guard_invitation_identity_erasure()
            RETURNS trigger
            LANGUAGE plpgsql
            SET search_path = ''
            AS $$
            BEGIN
                IF OLD.identity_erased_at IS NOT NULL AND (
                    NEW.identity_erased_at IS DISTINCT FROM OLD.identity_erased_at
                    OR NEW.invited_email IS NOT NULL
                    OR NEW.invited_email_normalized IS NOT NULL
                ) THEN
                    RAISE EXCEPTION 'Erased invitation identity cannot be restored.' USING ERRCODE = '55000';
                END IF;

                IF OLD.identity_erased_at IS NULL AND NEW.identity_erased_at IS NOT NULL AND (
                    (OLD.accepted_at IS NULL AND OLD.revoked_at IS NULL)
                    OR NEW.invited_email IS NOT NULL
                    OR NEW.invited_email_normalized IS NOT NULL
                ) THEN
                    RAISE EXCEPTION 'Only closed invitation identity may be erased.' USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END
            $$;

            CREATE TRIGGER company_invitations_identity_erasure_guard
            BEFORE UPDATE ON company_invitations
            FOR EACH ROW EXECUTE FUNCTION invumo_guard_invitation_identity_erasure();

            REVOKE ALL ON FUNCTION invumo_guard_invitation_identity_erasure() FROM PUBLIC;
            SQL);

        Schema::create('data_erasure_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('actor_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->text('action');
            $table->text('subject_type');
            $table->uuid('subject_id');
            $table->timestampTz('occurred_at');

            $table->index('actor_user_id');
            $table->index(['occurred_at', 'id']);
            $table->index(['subject_type', 'subject_id']);
        });
        DB::statement(<<<'SQL'
            ALTER TABLE data_erasure_events
            ADD CONSTRAINT data_erasure_events_kind_check
            CHECK (
                (action = 'COMPANY_ERASED' AND subject_type = 'COMPANY')
                OR
                (action = 'USER_ACCOUNT_ERASED' AND subject_type = 'USER_ACCOUNT')
            )
            SQL);

        DB::statement('REVOKE ALL ON TABLE data_erasure_events FROM PUBLIC');
        if (MigrationDatabaseRole::runtimeIsAvailable()) {
            DB::statement('GRANT SELECT, INSERT ON TABLE data_erasure_events TO invumo_runtime');
            DB::statement('GRANT EXECUTE ON FUNCTION invumo_guard_invitation_identity_erasure() TO invumo_runtime');
        }
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM company_invitations WHERE identity_erased_at IS NOT NULL) THEN
                    RAISE EXCEPTION 'Invitation identity erasure is irreversible; this migration cannot be reverted.';
                END IF;
            END
            $$
            SQL);
        Schema::dropIfExists('data_erasure_events');
        DB::statement('DROP TRIGGER IF EXISTS company_invitations_identity_erasure_guard ON company_invitations');
        DB::statement('DROP FUNCTION IF EXISTS invumo_guard_invitation_identity_erasure()');
        DB::statement('ALTER TABLE company_invitations DROP CONSTRAINT company_invitations_identity_erasure_check');
        DB::statement('ALTER TABLE company_invitations DROP CONSTRAINT company_invitations_acceptance_check');
        Schema::table('company_invitations', function (Blueprint $table): void {
            $table->dropColumn('identity_erased_at');
            $table->text('invited_email')->nullable(false)->change();
            $table->text('invited_email_normalized')->nullable(false)->change();
        });
        DB::statement(<<<'SQL'
            ALTER TABLE company_invitations
            ADD CONSTRAINT company_invitations_normalized_email_check
                CHECK (invited_email_normalized = lower(invited_email_normalized)),
            ADD CONSTRAINT company_invitations_acceptance_check
                CHECK (
                    (accepted_at IS NULL AND accepted_by_user_id IS NULL)
                    OR (accepted_at IS NOT NULL AND accepted_by_user_id IS NOT NULL)
                )
            SQL);
    }
};
