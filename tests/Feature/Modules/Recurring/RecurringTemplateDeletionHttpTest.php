<?php

namespace Tests\Feature\Modules\Recurring;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Customers\Models\Customer;
use App\Modules\Delivery\Data\JobDispatchStatus;
use App\Modules\Delivery\Models\JobDispatch;
use App\Modules\Documents\Models\Document;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Recurring\Actions\GenerateDueRecurringInvoices;
use App\Modules\Recurring\Actions\SyncRecurringDispatch;
use App\Modules\Recurring\Actions\TransitionRecurringTemplate;
use App\Modules\Recurring\Data\RecurringTemplateTransition;
use App\Modules\Recurring\Models\RecurringOccurrence;
use App\Modules\Recurring\Models\RecurringTemplate;
use App\Modules\Recurring\Models\RecurringTemplateLine;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithDeletionPreviews;
use Tests\TestCase;

final class RecurringTemplateDeletionHttpTest extends TestCase
{
    use DatabaseMigrations, InteractsWithDeletionPreviews;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_generated_invoice_dependency_is_visible_and_non_draft_deletion_preserves_history(): void
    {
        CarbonImmutable::setTestNow('2026-08-29 10:00:00 UTC');
        [$owner, $company, $template, $dispatch] = $this->scheduled();
        $this->actingAs($owner);

        $this->get(route('recurring.edit', [$company, $template]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('deletion.highRisk', true)
                ->where('deletion.guard.blocked', false));
        $this->delete(route('recurring.destroy', [$company, $template]), [
            'confirmed' => true,
            'deletion_state' => $this->recurringDeletionState($company, $template),
        ])->assertSessionHasErrors('template');

        app(GenerateDueRecurringInvoices::class)->handle($company->id, $dispatch->id, 1);
        $invoice = $this->tenant($company, fn (): Document => Document::query()->sole());

        $this->get(route('recurring.edit', [$company, $template]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('deletion.guard.blocked', true)
                ->where('deletion.guard.description', 'Generated Invoice occurrences still retained: 1.'));
        $this->delete(route('recurring.destroy', [$company, $template]), [
            'confirmed' => true, 'confirmed_high_risk' => true,
            'deletion_state' => $this->recurringDeletionState($company, $template),
        ])->assertSessionHasErrors('template');

        try {
            $this->tenant($company, fn (): bool => RecurringTemplate::query()
                ->whereKey($template->id)->delete());
            $this->fail('A generated occurrence must restrict template deletion.');
        } catch (QueryException $exception) {
            $this->assertContains($exception->errorInfo[0], ['23001', '23503']);
        }

        $this->delete(route('invoices.destroy', [$company, $invoice]), [
            'confirmed' => true,
            'confirmed_high_risk' => true,
            'confirmation_number' => $invoice->rendered_number,
            'deletion_state' => $this->invoiceDeletionState($company, $invoice),
        ])->assertRedirect();
        $this->delete(route('recurring.destroy', [$company, $template]), [
            'confirmed' => true, 'confirmed_high_risk' => true,
            'deletion_state' => $this->recurringDeletionState($company, $template),
        ])->assertRedirect(route('recurring.index', $company));

        $this->tenant($company, function () use ($template): void {
            $this->assertFalse(RecurringTemplate::query()->whereKey($template->id)->exists());
            $this->assertSame(0, RecurringOccurrence::query()->count());
            $this->assertSame(JobDispatchStatus::Cancelled, JobDispatch::query()->sole()->status);
            $audit = AuditEvent::query()
                ->where('action', 'company.recurring_template.deleted')->sole();
            $this->assertSame([
                'state' => 'ACTIVE', 'had_execution_history' => true,
            ], $audit->before);
            $this->assertSame(['deleted' => true], $audit->after);
        });
    }

    public function test_state_change_rejects_stale_recurring_deletion_confirmation(): void
    {
        [$owner, $company, $template] = $this->scheduled();
        $state = $this->recurringDeletionState($company, $template);
        app(TransitionRecurringTemplate::class)->handle(
            $company,
            $owner,
            $template->id,
            RecurringTemplateTransition::Pause,
            $this->tenant($company, fn (): int => RecurringTemplate::query()
                ->findOrFail($template->id)->edit_version),
            true,
        );
        $this->actingAs($owner)->delete(route('recurring.destroy', [$company, $template]), [
            'confirmed' => true,
            'confirmed_high_risk' => false,
            'deletion_state' => $state,
        ])->assertSessionHasErrors('template');

        $this->assertNotSame($state, $this->recurringDeletionState($company, $template));
    }

    /** @return array{User, Company, RecurringTemplate, JobDispatch} */
    private function scheduled(): array
    {
        $owner = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);
        $company = app(CreateCompany::class)->handle($account, $owner, 'Deletion Test SRL');

        return $this->tenant($company, function () use ($owner, $company): array {
            CompanySetting::query()->firstOrFail()->update([
                'timezone' => 'UTC', 'automation_local_time' => '09:00',
                'default_document_language' => 'en', 'default_payment_term_days' => 14,
            ]);
            CompanyCurrency::query()->create([
                'currency_code' => 'RON', 'currency_precision' => 2,
                'is_default' => true, 'active' => true,
            ]);
            $customer = Customer::query()->create([
                'type' => 'COMPANY', 'legal_name' => 'Customer SRL',
            ]);
            $template = RecurringTemplate::query()->create([
                'client_creation_key' => (string) Str::uuid7(),
                'internal_name' => 'Monthly service', 'customer_id' => $customer->id,
            ]);
            RecurringTemplateLine::query()->create([
                'recurring_template_id' => $template->id, 'position' => 1,
                'description' => 'Service', 'item_price' => '100', 'quantity' => '1',
                'period_unit' => 'NONE', 'discount_percentage' => '0',
                'tax_name' => null, 'tax_percentage' => '0',
            ]);
            $template->update([
                'state' => 'ACTIVE', 'recurrence_kind' => 'MONTHLY',
                'start_date' => '2026-08-29', 'schedule_anchor_ordinal' => 0,
                'next_logical_ordinal' => 0, 'next_occurrence_date' => '2026-08-29',
                'schedule_timezone' => 'UTC', 'schedule_local_time' => '09:00',
                'next_run_at' => '2026-08-29 09:00:00+00', 'activated_at' => now(),
            ]);

            return [$owner, $company, $template, app(SyncRecurringDispatch::class)->handle($template)];
        });
    }

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    private function tenant(Company $company, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
