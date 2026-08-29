<?php

namespace Tests\Feature\Modules\Recurring;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Customers\Models\Customer;
use App\Modules\Delivery\Data\JobDispatchStatus;
use App\Modules\Delivery\Models\CompanyReminderRule;
use App\Modules\Delivery\Models\DocumentReminderRule;
use App\Modules\Delivery\Models\JobDispatch;
use App\Modules\Documents\Models\Document;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Invoices\Actions\DeleteInvoice;
use App\Modules\Invoices\Data\InvoiceDeletionData;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Recurring\Actions\ExecuteRecurringGeneration;
use App\Modules\Recurring\Actions\GenerateDueRecurringInvoices;
use App\Modules\Recurring\Actions\SyncRecurringDispatch;
use App\Modules\Recurring\Data\RecurringJobResult;
use App\Modules\Recurring\Data\RecurringRunOutcome;
use App\Modules\Recurring\Models\RecurringOccurrence;
use App\Modules\Recurring\Models\RecurringTemplate;
use App\Modules\Recurring\Models\RecurringTemplateLine;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithDeletionPreviews;
use Tests\TestCase;

final class RecurringOccurrenceGenerationTest extends TestCase
{
    use DatabaseMigrations, InteractsWithDeletionPreviews;

    public function test_due_occurrence_creates_and_issues_one_invoice_idempotently(): void
    {
        CarbonImmutable::setTestNow('2026-08-29 10:00:00 UTC');
        $company = null;

        try {
            [$company, $template, $dispatch] = $this->scheduled('WEEKLY', '2026-08-29');
            $generate = app(GenerateDueRecurringInvoices::class);

            $this->assertSame(1, $generate->handle($company->id, $dispatch->id, 1));
            $this->tenant($company, function () use ($template, $dispatch): void {
                $document = Document::query()->sole();
                $invoice = Invoice::query()->sole();
                $occurrence = RecurringOccurrence::query()->sole();
                $template->refresh();
                $dispatch->refresh();

                $this->assertSame(InvoiceLifecycle::Issued, $invoice->lifecycle);
                $this->assertSame('2026-08-29', $document->issue_date?->toDateString());
                $this->assertSame('2026-09-12', $invoice->due_date?->toDateString());
                $this->assertSame('119.00000000', $document->total);
                $this->assertSame($document->id, $occurrence->invoice_id);
                $this->assertSame(JobDispatchStatus::Completed, $dispatch->status);
                $this->assertSame(1, $template->successful_occurrence_count);
                $this->assertSame(RecurringRunOutcome::Succeeded, $template->last_run_outcome);
                $this->assertSame('2026-09-05', $template->next_occurrence_date?->toDateString());
                $this->assertSame(1, DocumentReminderRule::query()->count());
                $this->assertSame([
                    'invoice_id' => $document->id,
                    'successful_occurrence_count' => 1,
                ], AuditEvent::query()
                    ->where('action', 'company.recurring_template.occurrence_generated')
                    ->sole()->after);
            });

            $this->assertSame(0, $generate->handle($company->id, $dispatch->id, 2));
            $this->tenant($company, function (): void {
                $this->assertSame(1, Document::query()->count());
                $this->assertSame(1, RecurringOccurrence::query()->count());
            });
        } finally {
            $company?->delete();
            CarbonImmutable::setTestNow();
        }
    }

    public function test_downtime_catch_up_is_oldest_first_and_uses_the_configured_bound(): void
    {
        CarbonImmutable::setTestNow('2026-08-20 10:00:00 UTC');
        config()->set('invumo.recurring.max_catch_up_occurrences', 3);
        $company = null;

        try {
            [$company, $template, $dispatch] = $this->scheduled(
                'CUSTOM', '2026-08-01', customCount: 1, customUnit: 'DAY',
            );
            $generate = app(GenerateDueRecurringInvoices::class);

            $this->assertSame(3, $generate->handle($company->id, $dispatch->id, 1));
            $nextDispatch = $this->tenant($company, function () use ($template): JobDispatch {
                $template->refresh();
                $this->assertSame(3, $template->successful_occurrence_count);
                $this->assertSame('2026-08-04', $template->next_occurrence_date?->toDateString());
                $this->assertSame(range(0, 2), RecurringOccurrence::query()
                    ->orderBy('logical_ordinal')->pluck('logical_ordinal')->all());

                return JobDispatch::query()
                    ->where('idempotency_key', SyncRecurringDispatch::key($template->id, 3))
                    ->sole();
            });
            $this->assertSame(3, $generate->handle($company->id, $nextDispatch->id, 1));
            $this->tenant($company, function () use ($template): void {
                $template->refresh();
                $this->assertSame(6, $template->successful_occurrence_count);
                $this->assertSame('2026-08-07', $template->next_occurrence_date?->toDateString());
                $this->assertSame(6, Document::query()->count());
            });
        } finally {
            $company?->delete();
            config()->set('invumo.recurring.max_catch_up_occurrences', 10);
            CarbonImmutable::setTestNow();
        }
    }

    public function test_deleting_generated_invoice_removes_occurrence_without_schedule_rewind(): void
    {
        CarbonImmutable::setTestNow('2026-08-29 10:00:00 UTC');
        $company = null;

        try {
            [$company, $template, $dispatch, $owner] = $this->scheduled(
                'WEEKLY', '2026-08-29',
            );
            $generate = app(GenerateDueRecurringInvoices::class);
            $generate->handle($company->id, $dispatch->id, 1);
            $document = $this->tenant($company, fn (): Document => Document::query()->sole());
            app(DeleteInvoice::class)->handle(
                $company,
                $owner,
                $document->id,
                new InvoiceDeletionData(
                    true,
                    true,
                    $document->rendered_number,
                    $this->invoiceDeletionState($company, $document),
                ),
            );
            $nextDispatch = $this->tenant($company, function () use ($template, $dispatch): JobDispatch {
                $template->refresh();
                $this->assertSame(0, Document::query()->count());
                $this->assertSame(0, RecurringOccurrence::query()->count());
                $this->assertFalse(JobDispatch::query()->whereKey($dispatch->id)->exists());
                $this->assertSame(1, $template->successful_occurrence_count);
                $this->assertSame(1, $template->next_logical_ordinal);

                return JobDispatch::query()
                    ->where('idempotency_key', SyncRecurringDispatch::key($template->id, 1))
                    ->sole();
            });
            $this->assertSame(0, $generate->handle($company->id, $dispatch->id, 2));

            CarbonImmutable::setTestNow('2026-09-05 10:00:00 UTC');
            $this->assertSame(1, $generate->handle($company->id, $nextDispatch->id, 1));
            $this->tenant($company, function () use ($template): void {
                $template->refresh();
                $this->assertSame(1, Document::query()->count());
                $this->assertSame(2, $template->successful_occurrence_count);
            });
        } finally {
            $company?->delete();
            CarbonImmutable::setTestNow();
        }
    }

    public function test_permanent_failure_is_visible_and_owner_can_retry_same_occurrence(): void
    {
        CarbonImmutable::setTestNow('2026-08-29 10:00:00 UTC');
        $company = null;

        try {
            [$company, $template, $dispatch, $owner] = $this->scheduled(
                'WEEKLY', '2026-08-29',
            );
            $member = User::factory()->create();
            $company->memberships()->create([
                'user_id' => $member->id, 'role' => CompanyRole::Member,
            ]);
            $this->tenant($company, fn (): bool => CompanySetting::query()
                ->firstOrFail()->update(['default_payment_term_days' => null]));

            $this->assertSame(
                RecurringJobResult::PermanentFailure,
                app(ExecuteRecurringGeneration::class)->handle(
                    $company->id, $dispatch->id, 1,
                ),
            );
            $this->tenant($company, function () use ($template, $dispatch): void {
                $this->assertSame(JobDispatchStatus::Failed, $dispatch->refresh()->status);
                $this->assertSame(RecurringRunOutcome::Failed, $template->refresh()->last_run_outcome);
                $this->assertSame(0, Document::query()->count());
            });

            $payload = ['edit_version' => 1, 'confirmed' => true];
            $this->actingAs($member)
                ->post(route('recurring.retry', [$company, $template]), $payload)
                ->assertForbidden();
            $this->actingAs($owner)
                ->post(route('recurring.retry', [$company, $template]), $payload)
                ->assertRedirect()->assertSessionDoesntHaveErrors();
            $this->tenant($company, function () use ($dispatch): void {
                $dispatch->refresh();
                $this->assertSame(JobDispatchStatus::Pending, $dispatch->status);
                $this->assertNull($dispatch->failure_category);
                CompanySetting::query()->firstOrFail()->update(['default_payment_term_days' => 14]);
            });
            $this->assertSame(1, app(GenerateDueRecurringInvoices::class)
                ->handle($company->id, $dispatch->id, 1));
        } finally {
            $company?->delete();
            CarbonImmutable::setTestNow();
        }
    }

    /** @return array{Company, RecurringTemplate, JobDispatch, User} */
    private function scheduled(
        string $kind,
        string $date,
        ?int $customCount = null,
        ?string $customUnit = null,
    ): array {
        $owner = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);
        $company = app(CreateCompany::class)->handle($account, $owner, 'Generation Test SRL');

        return $this->tenant($company, function () use ($company, $owner, $kind, $date, $customCount, $customUnit): array {
            CompanySetting::query()->firstOrFail()->update([
                'timezone' => 'UTC', 'automation_local_time' => '09:00',
                'default_document_language' => 'en', 'default_payment_term_days' => 14,
            ]);
            CompanyCurrency::query()->create([
                'currency_code' => 'RON', 'currency_precision' => 2,
                'is_default' => true, 'active' => true,
            ]);
            CompanyReminderRule::query()->create([
                'relation' => 'AFTER_DUE', 'day_offset' => 3,
                'enabled' => true, 'display_order' => 1,
            ]);
            $customer = Customer::query()->create([
                'type' => 'COMPANY', 'legal_name' => 'Generated Customer SRL',
            ]);
            $template = RecurringTemplate::query()->create([
                'client_creation_key' => (string) Str::uuid7(),
                'internal_name' => 'Generated service', 'customer_id' => $customer->id,
            ]);
            RecurringTemplateLine::query()->create([
                'recurring_template_id' => $template->id, 'position' => 1,
                'description' => 'Service', 'item_price' => '100', 'quantity' => '1',
                'period_unit' => 'NONE', 'discount_percentage' => '0',
                'tax_name' => 'VAT', 'tax_percentage' => '19',
            ]);
            $template->update([
                'state' => 'ACTIVE', 'recurrence_kind' => $kind,
                'custom_interval_count' => $customCount,
                'custom_interval_unit' => $customUnit,
                'start_date' => $date, 'schedule_anchor_ordinal' => 0,
                'next_logical_ordinal' => 0, 'next_occurrence_date' => $date,
                'schedule_timezone' => 'UTC', 'schedule_local_time' => '09:00',
                'next_run_at' => "{$date} 09:00:00+00", 'activated_at' => now(),
            ]);
            $dispatch = app(SyncRecurringDispatch::class)->handle($template);

            return [$company, $template, $dispatch, $owner];
        });
    }

    /** @template TReturn @param Closure(): TReturn $callback @return TReturn */
    private function tenant(Company $company, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
