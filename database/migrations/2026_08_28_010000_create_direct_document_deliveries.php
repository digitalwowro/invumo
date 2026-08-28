<?php

use App\Foundation\Database\Schema\TenantTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createArtifacts();
        $this->createDeliveries();
        $this->createRecipients();
        $this->createAttempts();
        $this->addConstraints();

        foreach (['document_artifacts', 'email_deliveries', 'email_delivery_recipients', 'email_delivery_attempts'] as $table) {
            TenantTable::protect($table);
        }
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS documents_pending_delivery_guard ON documents');
        Schema::dropIfExists('email_delivery_attempts');
        Schema::dropIfExists('email_delivery_recipients');
        Schema::dropIfExists('email_deliveries');
        Schema::dropIfExists('document_artifacts');
        DB::statement('DROP FUNCTION IF EXISTS public.invumo_email_delivery_attempt_finalize_only()');
        DB::statement('DROP FUNCTION IF EXISTS public.invumo_email_delivery_recipients_valid()');
        DB::statement('DROP FUNCTION IF EXISTS public.invumo_email_delivery_operational_update_only()');
        DB::statement('DROP FUNCTION IF EXISTS public.invumo_document_pending_delivery_guard()');
        DB::statement('DROP FUNCTION IF EXISTS public.invumo_reject_delivery_history_mutation()');
    }

    private function createArtifacts(): void
    {
        Schema::create('document_artifacts', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->uuid('document_id');
            $table->text('artifact_type');
            $table->unsignedBigInteger('document_edit_version');
            $table->text('storage_disk');
            $table->text('storage_key');
            $table->text('file_name');
            $table->text('mime_type');
            $table->unsignedBigInteger('byte_size');
            $table->char('sha256', 64);
            $table->timestampTz('generated_at');
            $table->timestampsTz();

            TenantTable::sameCompanyForeign($table, 'document_id', 'documents', 'document_artifacts_document_foreign', true);
            $table->unique(['company_id', 'storage_key'], 'document_artifacts_storage_key_unique');
            $table->index(['company_id', 'document_id', 'generated_at'], 'document_artifacts_document_time_index');
        });
    }

    private function createDeliveries(): void
    {
        Schema::create('email_deliveries', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->uuid('document_id')->nullable();
            $table->uuid('public_document_link_id')->nullable();
            $table->text('document_kind');
            $table->text('event_type');
            $table->uuid('delivery_key');
            $table->unsignedBigInteger('document_edit_version');
            $table->text('language_code');
            $table->text('subject')->nullable();
            $table->text('body')->nullable();
            $table->text('button_label')->nullable();
            $table->text('signature')->nullable();
            $table->text('button_url')->nullable();
            $table->text('attachment_mode')->nullable();
            $table->uuid('artifact_id')->nullable();
            $table->text('provider_name');
            $table->text('dispatch_state');
            $table->text('provider_message_identifier')->nullable();
            $table->text('failure_category')->nullable();
            $table->text('failure_summary')->nullable();
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampTz('redacted_at')->nullable();
            $table->foreignUuid('initiated_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            TenantTable::sameCompanyForeign($table, 'document_id', 'documents', 'email_deliveries_document_foreign');
            TenantTable::sameCompanyForeign($table, 'public_document_link_id', 'public_document_links', 'email_deliveries_public_link_foreign');
            TenantTable::sameCompanyForeign($table, 'artifact_id', 'document_artifacts', 'email_deliveries_artifact_foreign');
            $table->unique(['company_id', 'delivery_key'], 'email_deliveries_delivery_key_unique');
            $table->index(['company_id', 'document_id', 'created_at', 'id'], 'email_deliveries_document_time_index');
            $table->index(['company_id', 'public_document_link_id'], 'email_deliveries_public_link_index');
        });
    }

    private function createRecipients(): void
    {
        Schema::create('email_delivery_recipients', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->uuid('delivery_id');
            $table->text('role');
            $table->text('name')->nullable();
            $table->text('email');
            $table->unsignedInteger('display_order');
            $table->timestampsTz();

            TenantTable::sameCompanyForeign($table, 'delivery_id', 'email_deliveries', 'email_delivery_recipients_delivery_foreign', true);
            $table->unique(['company_id', 'delivery_id', 'role', 'display_order'], 'email_delivery_recipients_order_unique');
        });
    }

    private function createAttempts(): void
    {
        Schema::create('email_delivery_attempts', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->uuid('delivery_id');
            $table->unsignedSmallInteger('attempt_number');
            $table->uuid('client_reference')->nullable();
            $table->text('state');
            $table->text('provider_message_identifier')->nullable();
            $table->text('failure_category')->nullable();
            $table->text('failure_summary')->nullable();
            $table->timestampTz('submitted_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('redacted_at')->nullable();
            $table->timestampsTz();

            TenantTable::sameCompanyForeign($table, 'delivery_id', 'email_deliveries', 'email_delivery_attempts_delivery_foreign', true);
            $table->unique(['company_id', 'client_reference'], 'email_delivery_attempts_reference_unique');
            $table->unique(['company_id', 'delivery_id', 'attempt_number'], 'email_delivery_attempts_number_unique');
        });
    }

    private function addConstraints(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE document_artifacts
                ADD CONSTRAINT document_artifacts_type_check CHECK (artifact_type = 'PDF_ATTACHMENT'),
                ADD CONSTRAINT document_artifacts_version_check CHECK (document_edit_version >= 1),
                ADD CONSTRAINT document_artifacts_storage_check CHECK (
                    char_length(storage_disk) BETWEEN 1 AND 80
                    AND char_length(storage_key) BETWEEN 1 AND 500
                ),
                ADD CONSTRAINT document_artifacts_file_check CHECK (
                    char_length(file_name) BETWEEN 1 AND 150
                    AND mime_type = 'application/pdf'
                    AND byte_size BETWEEN 1 AND 11534336
                    AND sha256 ~ '^[0-9a-f]{64}$'
                );

            ALTER TABLE email_deliveries
                ADD CONSTRAINT email_deliveries_kind_event_check CHECK (
                    (document_kind = 'QUOTE' AND event_type = 'QUOTE_SENT')
                    OR (document_kind = 'INVOICE' AND event_type = 'INVOICE_SENT')
                ),
                ADD CONSTRAINT email_deliveries_language_check CHECK (language_code ~ '^[a-z]{2}(?:_[A-Z]{2})?$'),
                ADD CONSTRAINT email_deliveries_version_check CHECK (document_edit_version >= 1),
                ADD CONSTRAINT email_deliveries_content_check CHECK (
                    (redacted_at IS NULL AND document_id IS NOT NULL AND public_document_link_id IS NOT NULL
                        AND subject IS NOT NULL
                        AND body IS NOT NULL AND button_label IS NOT NULL AND button_url IS NOT NULL
                        AND attachment_mode IS NOT NULL)
                    OR (redacted_at IS NOT NULL AND document_id IS NULL AND public_document_link_id IS NULL
                        AND subject IS NULL
                        AND body IS NULL AND button_label IS NULL AND signature IS NULL
                        AND button_url IS NULL AND attachment_mode IS NULL AND artifact_id IS NULL)
                ),
                ADD CONSTRAINT email_deliveries_text_check CHECK (
                    (subject IS NULL OR (char_length(subject) BETWEEN 1 AND 500
                        AND subject !~ E'[\\r\\n]'))
                    AND (body IS NULL OR char_length(body) BETWEEN 1 AND 20000)
                    AND (button_label IS NULL OR (char_length(button_label) BETWEEN 1 AND 80
                        AND button_label !~ E'[\\r\\n]'))
                    AND (signature IS NULL OR char_length(signature) BETWEEN 1 AND 5000)
                    AND (button_url IS NULL OR char_length(button_url) BETWEEN 1 AND 2048)
                ),
                ADD CONSTRAINT email_deliveries_mode_artifact_check CHECK (
                    attachment_mode IS NULL
                    OR (attachment_mode = 'SECURE_LINK_ONLY' AND artifact_id IS NULL)
                    OR (attachment_mode = 'ATTACH_PDF' AND (
                        artifact_id IS NOT NULL OR dispatch_state <> 'ACCEPTED'
                    ))
                ),
                ADD CONSTRAINT email_deliveries_provider_check CHECK (provider_name = 'ZEPTOMAIL'),
                ADD CONSTRAINT email_deliveries_provider_identifier_check CHECK (
                    provider_message_identifier IS NULL
                    OR char_length(provider_message_identifier) BETWEEN 1 AND 500
                ),
                ADD CONSTRAINT email_deliveries_state_check CHECK (
                    dispatch_state IN ('QUEUED', 'RETRYING', 'ACCEPTED', 'REJECTED', 'UNKNOWN')
                ),
                ADD CONSTRAINT email_deliveries_state_timestamps_check CHECK (
                    redacted_at IS NOT NULL
                    OR (dispatch_state = 'QUEUED' AND accepted_at IS NULL AND failed_at IS NULL
                        AND provider_message_identifier IS NULL AND failure_category IS NULL)
                    OR (dispatch_state = 'RETRYING' AND accepted_at IS NULL AND failed_at IS NULL
                        AND failure_category IS NOT NULL)
                    OR (dispatch_state = 'ACCEPTED' AND accepted_at IS NOT NULL AND failed_at IS NULL
                        AND failure_category IS NULL)
                    OR (dispatch_state IN ('REJECTED', 'UNKNOWN') AND accepted_at IS NULL
                        AND failed_at IS NOT NULL AND failure_category IS NOT NULL)
                ),
                ADD CONSTRAINT email_deliveries_failure_check CHECK (
                    (redacted_at IS NULL AND (
                        (failure_category IS NULL AND failure_summary IS NULL)
                        OR (failure_category IS NOT NULL AND char_length(failure_category) BETWEEN 1 AND 80
                            AND failure_summary IS NOT NULL AND char_length(failure_summary) BETWEEN 1 AND 500)
                    ))
                    OR (redacted_at IS NOT NULL AND failure_summary IS NULL
                        AND (failure_category IS NULL OR char_length(failure_category) BETWEEN 1 AND 80))
                );

            ALTER TABLE email_delivery_recipients
                ADD CONSTRAINT email_delivery_recipients_role_check CHECK (role IN ('TO', 'CC', 'BCC')),
                ADD CONSTRAINT email_delivery_recipients_name_check CHECK (name IS NULL OR char_length(name) BETWEEN 1 AND 160),
                ADD CONSTRAINT email_delivery_recipients_email_check CHECK (
                    email = btrim(email) AND email = lower(email) AND char_length(email) BETWEEN 1 AND 254
                ),
                ADD CONSTRAINT email_delivery_recipients_order_check CHECK (display_order >= 1);

            ALTER TABLE email_delivery_attempts
                ADD CONSTRAINT email_delivery_attempts_number_check CHECK (attempt_number >= 1),
                ADD CONSTRAINT email_delivery_attempts_state_check CHECK (
                    state IN ('PENDING', 'ACCEPTED', 'RETRYABLE_REJECTION', 'PERMANENT_REJECTION', 'UNKNOWN')
                ),
                ADD CONSTRAINT email_delivery_attempts_provider_identifier_check CHECK (
                    provider_message_identifier IS NULL
                    OR char_length(provider_message_identifier) BETWEEN 1 AND 500
                ),
                ADD CONSTRAINT email_delivery_attempts_state_timestamps_check CHECK (
                    redacted_at IS NOT NULL
                    OR (state = 'PENDING' AND completed_at IS NULL
                        AND provider_message_identifier IS NULL AND failure_category IS NULL)
                    OR (state = 'ACCEPTED' AND completed_at IS NOT NULL AND failure_category IS NULL)
                    OR (state IN ('RETRYABLE_REJECTION', 'PERMANENT_REJECTION', 'UNKNOWN')
                        AND completed_at IS NOT NULL AND failure_category IS NOT NULL)
                ),
                ADD CONSTRAINT email_delivery_attempts_failure_check CHECK (
                    (redacted_at IS NULL AND client_reference IS NOT NULL AND (
                        (failure_category IS NULL AND failure_summary IS NULL)
                        OR (failure_category IS NOT NULL AND char_length(failure_category) BETWEEN 1 AND 80
                            AND failure_summary IS NOT NULL AND char_length(failure_summary) BETWEEN 1 AND 500)
                    ))
                    OR (redacted_at IS NOT NULL AND client_reference IS NULL
                        AND provider_message_identifier IS NULL AND failure_summary IS NULL
                        AND (failure_category IS NULL OR char_length(failure_category) BETWEEN 1 AND 80))
                );

            CREATE UNIQUE INDEX email_delivery_attempts_one_pending_unique
                ON email_delivery_attempts (company_id, delivery_id)
                WHERE state = 'PENDING';

            CREATE UNIQUE INDEX email_deliveries_one_pending_per_document_unique
                ON email_deliveries (company_id, document_id)
                WHERE document_id IS NOT NULL AND dispatch_state IN ('QUEUED', 'RETRYING');

            CREATE OR REPLACE FUNCTION public.invumo_reject_delivery_history_mutation()
            RETURNS trigger
            LANGUAGE plpgsql
            SET search_path = pg_catalog, public
            AS $function$
            BEGIN
                RAISE EXCEPTION USING ERRCODE = '23001', MESSAGE = TG_TABLE_NAME || ' is immutable';
            END;
            $function$;

            CREATE TRIGGER document_artifacts_immutable
            BEFORE UPDATE ON document_artifacts
            FOR EACH ROW EXECUTE FUNCTION public.invumo_reject_delivery_history_mutation();

            CREATE TRIGGER email_delivery_recipients_immutable
            BEFORE UPDATE ON email_delivery_recipients
            FOR EACH ROW EXECUTE FUNCTION public.invumo_reject_delivery_history_mutation();

            CREATE OR REPLACE FUNCTION public.invumo_email_delivery_recipients_valid()
            RETURNS trigger
            LANGUAGE plpgsql
            SET search_path = pg_catalog, public
            AS $function$
            DECLARE
                target_company uuid := COALESCE(NEW.company_id, OLD.company_id);
                target_delivery uuid;
                delivery_redacted timestamptz;
            BEGIN
                IF TG_TABLE_NAME = 'email_deliveries' THEN
                    target_delivery := COALESCE(NEW.id, OLD.id);
                ELSE
                    target_delivery := COALESCE(NEW.delivery_id, OLD.delivery_id);
                END IF;

                SELECT delivery.redacted_at
                INTO delivery_redacted
                FROM public.email_deliveries AS delivery
                WHERE delivery.company_id = target_company
                  AND delivery.id = target_delivery;

                IF NOT FOUND OR delivery_redacted IS NOT NULL THEN
                    RETURN NULL;
                END IF;

                IF (SELECT count(*) FROM public.email_delivery_recipients AS recipient
                        WHERE recipient.company_id = target_company
                          AND recipient.delivery_id = target_delivery) > 100
                    OR NOT EXISTS (
                        SELECT 1 FROM public.email_delivery_recipients AS recipient
                        WHERE recipient.company_id = target_company
                          AND recipient.delivery_id = target_delivery
                          AND recipient.role = 'TO'
                    )
                    OR EXISTS (
                        SELECT 1 FROM public.email_delivery_recipients AS recipient
                        WHERE recipient.company_id = target_company
                          AND recipient.delivery_id = target_delivery
                        GROUP BY recipient.email
                        HAVING count(*) > 1
                    )
                THEN
                    RAISE EXCEPTION USING ERRCODE = '23514', MESSAGE = 'email delivery recipients are invalid';
                END IF;

                RETURN NULL;
            END;
            $function$;

            CREATE CONSTRAINT TRIGGER email_deliveries_recipient_integrity
            AFTER INSERT OR UPDATE OF redacted_at ON email_deliveries
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW EXECUTE FUNCTION public.invumo_email_delivery_recipients_valid();

            CREATE CONSTRAINT TRIGGER email_delivery_recipients_integrity
            AFTER INSERT OR UPDATE OR DELETE ON email_delivery_recipients
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW EXECUTE FUNCTION public.invumo_email_delivery_recipients_valid();

            CREATE OR REPLACE FUNCTION public.invumo_email_delivery_attempt_finalize_only()
            RETURNS trigger
            LANGUAGE plpgsql
            SET search_path = pg_catalog, public
            AS $function$
            BEGIN
                IF OLD.redacted_at IS NULL AND NEW.redacted_at IS NOT NULL THEN
                    IF NEW.id IS DISTINCT FROM OLD.id
                        OR NEW.company_id IS DISTINCT FROM OLD.company_id
                        OR NEW.delivery_id IS DISTINCT FROM OLD.delivery_id
                        OR NEW.attempt_number IS DISTINCT FROM OLD.attempt_number
                        OR NEW.state IS DISTINCT FROM OLD.state
                        OR NEW.failure_category IS DISTINCT FROM OLD.failure_category
                        OR NEW.submitted_at IS DISTINCT FROM OLD.submitted_at
                        OR NEW.completed_at IS DISTINCT FROM OLD.completed_at
                        OR NEW.created_at IS DISTINCT FROM OLD.created_at
                        OR NEW.client_reference IS NOT NULL
                        OR NEW.provider_message_identifier IS NOT NULL
                        OR NEW.failure_summary IS NOT NULL
                        OR NOT EXISTS (
                            SELECT 1 FROM public.email_deliveries AS delivery
                            WHERE delivery.company_id = NEW.company_id
                              AND delivery.id = NEW.delivery_id
                              AND delivery.redacted_at IS NOT NULL
                        )
                    THEN
                        RAISE EXCEPTION USING ERRCODE = '23001', MESSAGE = 'email delivery attempt redaction is invalid';
                    END IF;

                    RETURN NEW;
                END IF;

                IF OLD.redacted_at IS NOT NULL
                    OR OLD.state <> 'PENDING'
                    OR NEW.id IS DISTINCT FROM OLD.id
                    OR NEW.company_id IS DISTINCT FROM OLD.company_id
                    OR NEW.delivery_id IS DISTINCT FROM OLD.delivery_id
                    OR NEW.attempt_number IS DISTINCT FROM OLD.attempt_number
                    OR NEW.client_reference IS DISTINCT FROM OLD.client_reference
                    OR NEW.redacted_at IS DISTINCT FROM OLD.redacted_at
                    OR NEW.submitted_at IS DISTINCT FROM OLD.submitted_at
                    OR NEW.created_at IS DISTINCT FROM OLD.created_at
                THEN
                    RAISE EXCEPTION USING ERRCODE = '23001', MESSAGE = 'email_delivery_attempts is immutable after finalization';
                END IF;

                RETURN NEW;
            END;
            $function$;

            CREATE TRIGGER email_delivery_attempts_finalize_only
            BEFORE UPDATE ON email_delivery_attempts
            FOR EACH ROW EXECUTE FUNCTION public.invumo_email_delivery_attempt_finalize_only();

            CREATE OR REPLACE FUNCTION public.invumo_email_delivery_operational_update_only()
            RETURNS trigger
            LANGUAGE plpgsql
            SET search_path = pg_catalog, public
            AS $function$
            BEGIN
                IF NEW.id IS DISTINCT FROM OLD.id
                    OR NEW.company_id IS DISTINCT FROM OLD.company_id
                    OR NEW.document_kind IS DISTINCT FROM OLD.document_kind
                    OR NEW.event_type IS DISTINCT FROM OLD.event_type
                    OR NEW.delivery_key IS DISTINCT FROM OLD.delivery_key
                    OR NEW.document_edit_version IS DISTINCT FROM OLD.document_edit_version
                    OR NEW.language_code IS DISTINCT FROM OLD.language_code
                    OR NEW.provider_name IS DISTINCT FROM OLD.provider_name
                    OR NEW.created_at IS DISTINCT FROM OLD.created_at
                    OR (OLD.redacted_at IS NULL AND NEW.redacted_at IS NOT NULL AND (
                        NEW.dispatch_state IS DISTINCT FROM OLD.dispatch_state
                        OR NEW.accepted_at IS DISTINCT FROM OLD.accepted_at
                        OR NEW.failed_at IS DISTINCT FROM OLD.failed_at
                        OR NEW.failure_category IS DISTINCT FROM OLD.failure_category
                        OR NEW.document_id IS NOT NULL
                        OR NEW.public_document_link_id IS NOT NULL
                        OR NEW.subject IS NOT NULL
                        OR NEW.body IS NOT NULL
                        OR NEW.button_label IS NOT NULL
                        OR NEW.signature IS NOT NULL
                        OR NEW.button_url IS NOT NULL
                        OR NEW.attachment_mode IS NOT NULL
                        OR NEW.artifact_id IS NOT NULL
                        OR NEW.provider_message_identifier IS NOT NULL
                        OR NEW.failure_summary IS NOT NULL
                        OR NEW.initiated_by_user_id IS NOT NULL
                    ))
                    OR (
                        NEW.redacted_at IS NULL
                        AND (
                            NEW.document_id IS DISTINCT FROM OLD.document_id
                            OR NEW.public_document_link_id IS DISTINCT FROM OLD.public_document_link_id
                            OR NEW.subject IS DISTINCT FROM OLD.subject
                            OR NEW.body IS DISTINCT FROM OLD.body
                            OR NEW.button_label IS DISTINCT FROM OLD.button_label
                            OR NEW.signature IS DISTINCT FROM OLD.signature
                            OR NEW.button_url IS DISTINCT FROM OLD.button_url
                            OR NEW.attachment_mode IS DISTINCT FROM OLD.attachment_mode
                            OR (
                                NEW.artifact_id IS DISTINCT FROM OLD.artifact_id
                                AND NOT (
                                    OLD.artifact_id IS NULL
                                    AND NEW.artifact_id IS NOT NULL
                                    AND OLD.attachment_mode = 'ATTACH_PDF'
                                    AND OLD.dispatch_state IN ('QUEUED', 'RETRYING')
                                )
                            )
                            OR NEW.initiated_by_user_id IS DISTINCT FROM OLD.initiated_by_user_id
                        )
                    )
                    OR (OLD.redacted_at IS NOT NULL AND NEW IS DISTINCT FROM OLD)
                THEN
                    RAISE EXCEPTION USING ERRCODE = '23001', MESSAGE = 'email delivery content is immutable';
                END IF;

                RETURN NEW;
            END;
            $function$;

            CREATE TRIGGER email_deliveries_operational_update_only
            BEFORE UPDATE ON email_deliveries
            FOR EACH ROW EXECUTE FUNCTION public.invumo_email_delivery_operational_update_only();

            CREATE OR REPLACE FUNCTION public.invumo_document_pending_delivery_guard()
            RETURNS trigger
            LANGUAGE plpgsql
            SET search_path = pg_catalog, public
            AS $function$
            BEGIN
                IF (OLD.edit_version IS DISTINCT FROM NEW.edit_version
                        OR OLD.content_version IS DISTINCT FROM NEW.content_version)
                    AND EXISTS (
                        SELECT 1
                        FROM public.email_deliveries AS delivery
                        WHERE delivery.company_id = NEW.company_id
                          AND delivery.document_id = NEW.id
                          AND delivery.dispatch_state IN ('QUEUED', 'RETRYING')
                          AND delivery.document_edit_version = OLD.edit_version
                    )
                THEN
                    RAISE EXCEPTION USING ERRCODE = '23514', MESSAGE = 'document has a pending email delivery';
                END IF;

                RETURN NULL;
            END;
            $function$;

            CREATE CONSTRAINT TRIGGER documents_pending_delivery_guard
            AFTER UPDATE OF edit_version, content_version ON documents
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW EXECUTE FUNCTION public.invumo_document_pending_delivery_guard();
            SQL);
    }
};
