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
        Schema::table('job_dispatches', function (Blueprint $table): void {
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->text('failure_category')->nullable();
            $table->text('failure_summary')->nullable();
        });
        Schema::table('recurring_templates', function (Blueprint $table): void {
            $table->timestampTz('last_run_started_at')->nullable();
            $table->timestampTz('last_run_completed_at')->nullable();
            $table->text('last_run_outcome')->nullable();
            $table->text('last_failure_category')->nullable();
        });

        $this->createOccurrences();
        $this->constraints();
        TenantTable::protect('recurring_occurrences');
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_occurrences');
        $this->deleteRecurringDispatches();
        DB::unprepared(<<<'SQL'
            ALTER TABLE recurring_templates
                DROP CONSTRAINT recurring_templates_last_run_check;
            ALTER TABLE job_dispatches
                DROP CONSTRAINT job_dispatches_execution_check,
                DROP CONSTRAINT job_dispatches_type_check,
                DROP CONSTRAINT job_dispatches_status_check;
            ALTER TABLE job_dispatches
                ADD CONSTRAINT job_dispatches_type_check CHECK (job_type = 'INVOICE_REMINDER'),
                ADD CONSTRAINT job_dispatches_status_check
                    CHECK (status IN ('PENDING', 'QUEUED', 'COMPLETED', 'CANCELLED'));
            SQL);
        Schema::table('recurring_templates', function (Blueprint $table): void {
            $table->dropColumn([
                'last_run_started_at', 'last_run_completed_at',
                'last_run_outcome', 'last_failure_category',
            ]);
        });
        Schema::table('job_dispatches', function (Blueprint $table): void {
            $table->dropColumn([
                'attempt_count', 'started_at', 'completed_at',
                'failure_category', 'failure_summary',
            ]);
        });
    }

    private function createOccurrences(): void
    {
        Schema::create('recurring_occurrences', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->uuid('recurring_template_id');
            $table->uuid('job_dispatch_id');
            $table->text('occurrence_key');
            $table->unsignedBigInteger('logical_ordinal');
            $table->date('scheduled_local_date');
            $table->time('scheduled_local_time');
            $table->text('schedule_timezone');
            $table->timestampTz('scheduled_at');
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at');
            $table->unsignedSmallInteger('attempt_count');
            $table->text('outcome');
            $table->uuid('invoice_id');
            $table->timestampsTz();

            TenantTable::sameCompanyForeign(
                $table, 'recurring_template_id', 'recurring_templates',
                'recurring_occurrences_company_template_foreign',
            );
            TenantTable::sameCompanyForeign(
                $table, 'job_dispatch_id', 'job_dispatches',
                'recurring_occurrences_company_dispatch_foreign',
            );
            $table->foreign(
                ['company_id', 'invoice_id'], 'recurring_occurrences_company_invoice_foreign',
            )->references(['company_id', 'document_id'])->on('invoices')->restrictOnDelete();
            $table->index(
                ['company_id', 'invoice_id'], 'recurring_occurrences_company_invoice_index',
            );
            $table->unique(
                ['company_id', 'recurring_template_id', 'occurrence_key'],
                'recurring_occurrences_template_key_unique',
            );
            $table->unique(
                ['company_id', 'recurring_template_id', 'logical_ordinal'],
                'recurring_occurrences_template_ordinal_unique',
            );
            $table->unique(
                ['company_id', 'job_dispatch_id'], 'recurring_occurrences_dispatch_unique',
            );
            $table->unique(
                ['company_id', 'invoice_id'], 'recurring_occurrences_invoice_unique',
            );
        });
    }

    private function constraints(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE job_dispatches
                DROP CONSTRAINT job_dispatches_type_check,
                DROP CONSTRAINT job_dispatches_status_check,
                ADD CONSTRAINT job_dispatches_type_check
                    CHECK (job_type IN ('INVOICE_REMINDER', 'RECURRING_OCCURRENCE')),
                ADD CONSTRAINT job_dispatches_status_check
                    CHECK (status IN ('PENDING', 'QUEUED', 'COMPLETED', 'CANCELLED', 'FAILED')),
                ADD CONSTRAINT job_dispatches_execution_check CHECK (
                    attempt_count BETWEEN 0 AND 100
                    AND (failure_category IS NULL OR char_length(failure_category) BETWEEN 1 AND 80)
                    AND (failure_summary IS NULL OR char_length(failure_summary) BETWEEN 1 AND 500)
                    AND (status <> 'FAILED' OR (completed_at IS NOT NULL
                        AND failure_category IS NOT NULL AND failure_summary IS NOT NULL))
                );

            ALTER TABLE recurring_templates
                ADD CONSTRAINT recurring_templates_last_run_check CHECK (
                    (last_run_outcome IS NULL AND last_run_started_at IS NULL
                        AND last_run_completed_at IS NULL AND last_failure_category IS NULL)
                    OR (last_run_outcome IN ('SUCCEEDED', 'FAILED', 'SKIPPED')
                        AND last_run_started_at IS NOT NULL AND last_run_completed_at IS NOT NULL
                        AND (last_run_outcome = 'FAILED') = (last_failure_category IS NOT NULL))
                );

            ALTER TABLE recurring_occurrences
                ADD CONSTRAINT recurring_occurrences_key_check CHECK (
                    occurrence_key ~ '^ordinal:[0-9]+$' AND char_length(occurrence_key) <= 80
                ),
                ADD CONSTRAINT recurring_occurrences_schedule_check CHECK (
                    scheduled_local_date BETWEEN DATE '0001-01-01' AND DATE '9999-12-31'
                    AND char_length(schedule_timezone) BETWEEN 1 AND 100
                    AND completed_at >= started_at AND attempt_count BETWEEN 1 AND 100
                ),
                ADD CONSTRAINT recurring_occurrences_outcome_check CHECK (outcome = 'SUCCEEDED');
            SQL);
    }

    private function deleteRecurringDispatches(): void
    {
        foreach (DB::table('companies')->orderBy('id')->pluck('id') as $companyId) {
            DB::transaction(function () use ($companyId): void {
                DB::statement("SELECT set_config('app.current_company_id', ?, true)", [$companyId]);
                DB::table('job_dispatches')->where('job_type', 'RECURRING_OCCURRENCE')->delete();
            });
        }
    }
};
