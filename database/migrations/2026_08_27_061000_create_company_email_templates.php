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
        Schema::create('company_email_templates', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->text('event_type');
            $table->text('language_code');
            $table->text('subject');
            $table->text('body');
            $table->text('button_label');
            $table->text('signature')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['company_id', 'event_type', 'language_code'],
                'company_email_templates_event_language_unique',
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE company_email_templates
                ADD CONSTRAINT company_email_templates_event_check CHECK (
                    event_type IN (
                        'QUOTE_SENT',
                        'INVOICE_SENT',
                        'PAYMENT_REMINDER',
                        'PAYMENT_RECEIVED'
                    )
                ),
                ADD CONSTRAINT company_email_templates_language_check CHECK (
                    language_code = btrim(language_code)
                    AND char_length(language_code) BETWEEN 2 AND 35
                    AND language_code ~ '^[A-Za-z]{2,8}([-_][A-Za-z0-9]{1,8})*$'
                ),
                ADD CONSTRAINT company_email_templates_subject_check CHECK (
                    subject = btrim(subject)
                    AND char_length(subject) BETWEEN 1 AND 500
                    AND subject !~ E'[\\r\\n]'
                ),
                ADD CONSTRAINT company_email_templates_body_check CHECK (
                    body = btrim(body)
                    AND char_length(body) BETWEEN 1 AND 20000
                ),
                ADD CONSTRAINT company_email_templates_button_label_check CHECK (
                    button_label = btrim(button_label)
                    AND char_length(button_label) BETWEEN 1 AND 80
                    AND button_label !~ E'[\\r\\n]'
                ),
                ADD CONSTRAINT company_email_templates_signature_check CHECK (
                    signature IS NULL OR (
                        signature = btrim(signature)
                        AND char_length(signature) BETWEEN 1 AND 5000
                    )
                )
            SQL);

        TenantTable::protect('company_email_templates');
    }

    public function down(): void
    {
        Schema::dropIfExists('company_email_templates');
    }
};
