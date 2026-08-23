<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('owning_account_id')
                ->constrained('accounts')
                ->restrictOnDelete();
            $table->text('name');
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();

            $table->index('owning_account_id');
            $table->index(['archived_at', 'name']);
        });

        Schema::create('company_memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();
            $table->foreignUuid('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->text('role');
            $table->timestampsTz();

            $table->unique(['company_id', 'user_id']);
            $table->index('user_id');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE company_memberships
            ADD CONSTRAINT company_memberships_role_check
            CHECK (role IN ('OWNER', 'ADMIN', 'MEMBER'))
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX company_memberships_one_owner_unique
            ON company_memberships (company_id)
            WHERE role = 'OWNER'
            SQL);

        Schema::create('company_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();
            $table->text('invited_email');
            $table->text('invited_email_normalized');
            $table->text('role');
            $table->text('token_hash')->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampTz('accepted_at')->nullable();
            $table->foreignUuid('accepted_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignUuid('invited_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestampsTz();

            $table->index(['company_id', 'invited_email_normalized']);
            $table->index('accepted_by_user_id');
            $table->index('invited_by_user_id');
            $table->index(['company_id', 'expires_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE company_invitations
            ADD CONSTRAINT company_invitations_role_check
            CHECK (role IN ('ADMIN', 'MEMBER')),
            ADD CONSTRAINT company_invitations_normalized_email_check
            CHECK (invited_email_normalized = lower(invited_email_normalized)),
            ADD CONSTRAINT company_invitations_acceptance_check
            CHECK (
                (accepted_at IS NULL AND accepted_by_user_id IS NULL)
                OR (accepted_at IS NOT NULL AND accepted_by_user_id IS NOT NULL)
            )
            SQL);

        $this->createOwnerConstraint();
        $this->grantRuntimePrivileges();
    }

    public function down(): void
    {
        Schema::dropIfExists('company_invitations');
        Schema::dropIfExists('company_memberships');
        Schema::dropIfExists('companies');
        DB::statement('DROP FUNCTION IF EXISTS invumo_check_company_owner()');
        DB::statement('DROP FUNCTION IF EXISTS invumo_assert_company_owner(uuid)');
    }

    private function createOwnerConstraint(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION invumo_assert_company_owner(target_company_id uuid)
            RETURNS void
            LANGUAGE plpgsql
            SET search_path = ''
            AS $$
            DECLARE
                owner_count bigint;
                owner_matches_account boolean;
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM public.companies WHERE id = target_company_id
                ) THEN
                    RETURN;
                END IF;

                SELECT
                    count(m.id),
                    coalesce(bool_or(m.user_id = a.owner_user_id), false)
                INTO owner_count, owner_matches_account
                FROM public.companies c
                JOIN public.accounts a ON a.id = c.owning_account_id
                LEFT JOIN public.company_memberships m
                    ON m.company_id = c.id AND m.role = 'OWNER'
                WHERE c.id = target_company_id
                GROUP BY a.owner_user_id;

                IF owner_count <> 1 OR NOT owner_matches_account THEN
                    RAISE EXCEPTION
                        'Company % must have exactly one Owner matching its owning Account.',
                        target_company_id
                        USING ERRCODE = '23514';
                END IF;
            END
            $$;

            CREATE OR REPLACE FUNCTION invumo_check_company_owner()
            RETURNS trigger
            LANGUAGE plpgsql
            SET search_path = ''
            AS $$
            BEGIN
                IF TG_TABLE_NAME = 'companies' THEN
                    PERFORM public.invumo_assert_company_owner(coalesce(NEW.id, OLD.id));
                ELSE
                    IF TG_OP <> 'INSERT' THEN
                        PERFORM public.invumo_assert_company_owner(OLD.company_id);
                    END IF;

                    IF TG_OP <> 'DELETE' AND (TG_OP = 'INSERT' OR NEW.company_id <> OLD.company_id) THEN
                        PERFORM public.invumo_assert_company_owner(NEW.company_id);
                    END IF;
                END IF;

                RETURN NULL;
            END
            $$;

            CREATE CONSTRAINT TRIGGER companies_valid_owner
            AFTER INSERT OR UPDATE OR DELETE ON companies
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW EXECUTE FUNCTION invumo_check_company_owner();

            CREATE CONSTRAINT TRIGGER company_memberships_valid_owner
            AFTER INSERT OR UPDATE OR DELETE ON company_memberships
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW EXECUTE FUNCTION invumo_check_company_owner();

            REVOKE ALL ON FUNCTION invumo_assert_company_owner(uuid) FROM PUBLIC;
            REVOKE ALL ON FUNCTION invumo_check_company_owner() FROM PUBLIC;
            SQL);
    }

    private function grantRuntimePrivileges(): void
    {
        DB::unprepared(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'invumo_runtime') THEN
                    EXECUTE 'GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE companies, company_memberships, company_invitations TO invumo_runtime';
                    EXECUTE 'GRANT EXECUTE ON FUNCTION invumo_assert_company_owner(uuid), invumo_check_company_owner() TO invumo_runtime';
                END IF;
            END
            $$
            SQL);
    }
};
