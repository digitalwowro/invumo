<?php

use App\Foundation\Database\Schema\MigrationDatabaseRole;
use App\Foundation\Database\Schema\TenantTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $runtimeIsAvailable = MigrationDatabaseRole::runtimeIsAvailable();

        Schema::table('company_settings', function (Blueprint $table): void {
            $table->boolean('public_links_enabled_by_default')->default(true);
            $table->smallInteger('default_public_link_validity_days')->default(30);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE company_settings
                ADD CONSTRAINT company_settings_public_link_validity_days_check
                    CHECK (default_public_link_validity_days BETWEEN 1 AND 3650)
            SQL);

        Schema::create('public_document_links', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->uuid('document_id');
            $table->integer('generation');
            $table->text('token_hash');
            $table->text('token_ciphertext');
            $table->timestampTz('expires_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->uuid('revoked_by_user_id')->nullable();
            $table->text('revocation_kind')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->timestampsTz();

            TenantTable::sameCompanyForeign(
                $table,
                'document_id',
                'documents',
                'public_document_links_company_document_foreign',
            );
            $table->foreign('revoked_by_user_id')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by_user_id')
                ->references('id')->on('users')->nullOnDelete();
            $table->unique('token_hash', 'public_document_links_token_hash_unique');
            $table->unique(
                ['company_id', 'document_id', 'generation'],
                'public_document_links_document_generation_unique',
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE public_document_links
                ADD CONSTRAINT public_document_links_generation_check
                    CHECK (generation > 0),
                ADD CONSTRAINT public_document_links_token_hash_check
                    CHECK (token_hash ~ '^[0-9a-f]{64}$'),
                ADD CONSTRAINT public_document_links_ciphertext_check
                    CHECK (char_length(token_ciphertext) BETWEEN 1 AND 2048),
                ADD CONSTRAINT public_document_links_expiry_check
                    CHECK (expires_at > created_at),
                ADD CONSTRAINT public_document_links_revocation_check
                    CHECK (
                        (revoked_at IS NULL AND revoked_by_user_id IS NULL AND revocation_kind IS NULL)
                        OR
                        (
                            revoked_at IS NOT NULL
                            AND revocation_kind IN ('EXPLICIT', 'REGENERATED')
                            AND (revocation_kind <> 'EXPLICIT' OR revoked_by_user_id IS NOT NULL)
                        )
                    )
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX public_document_links_one_current_unique
            ON public_document_links (company_id, document_id)
            WHERE revoked_at IS NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX public_document_links_active_document_index
            ON public_document_links (company_id, document_id, expires_at)
            WHERE revoked_at IS NULL
            SQL);

        TenantTable::protect('public_document_links');

        if ($runtimeIsAvailable) {
            DB::statement(<<<'SQL'
                CREATE POLICY public_document_links_token_bootstrap_policy
                ON public.public_document_links
                FOR SELECT
                TO invumo_runtime
                USING (
                    token_hash = nullif(current_setting('app.public_link_hash', true), '')
                    AND revoked_at IS NULL
                    AND expires_at > statement_timestamp()
                )
                SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('public_document_links');

        DB::statement(<<<'SQL'
            ALTER TABLE company_settings
                DROP CONSTRAINT company_settings_public_link_validity_days_check
            SQL);

        Schema::table('company_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'public_links_enabled_by_default',
                'default_public_link_validity_days',
            ]);
        });
    }
};
