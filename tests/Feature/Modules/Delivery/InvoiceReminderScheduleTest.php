<?php

namespace Tests\Feature\Modules\Delivery;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Delivery\Actions\ClaimDueJobDispatches;
use App\Modules\Delivery\Actions\PrepareInvoiceReminder;
use App\Modules\Delivery\Contracts\SendsProviderEmail;
use App\Modules\Delivery\Data\JobDispatchStatus;
use App\Modules\Delivery\Data\ProviderDelivery;
use App\Modules\Delivery\Data\ProviderDeliveryResult;
use App\Modules\Delivery\Data\ReminderInstanceStatus;
use App\Modules\Delivery\Jobs\SendDocumentDelivery;
use App\Modules\Delivery\Jobs\SendInvoiceReminder;
use App\Modules\Delivery\Models\CompanyReminderRule;
use App\Modules\Delivery\Models\DocumentReminderRule;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Delivery\Models\JobDispatch;
use App\Modules\Delivery\Models\ReminderInstance;
use App\Modules\Documents\Models\DocumentLine;
use App\Modules\Invoices\Actions\IssueInvoice;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\DocumentDeliveryTestCase;

final class InvoiceReminderScheduleTest extends DocumentDeliveryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-08-28 12:00:00 Europe/Bucharest');
    }

    protected function tearDown(): void
    {
        Date::setTestNow();
        parent::tearDown();
    }

    public function test_issue_snapshots_rules_and_materializes_company_local_dispatches(): void
    {
        [$owner, $company] = $this->company();
        $this->rule($company, 'BEFORE_DUE', 30);
        $invoice = $this->completeInvoice($company, $this->invoice($company, $owner));

        app(IssueInvoice::class)->handle($company, $owner, $invoice->id, $invoice->edit_version);

        $this->tenant($company, function (): void {
            $instance = ReminderInstance::query()->sole();
            $dispatch = JobDispatch::query()->sole();
            $this->assertSame(ReminderInstanceStatus::Pending, $instance->status);
            $this->assertSame('2026-08-28', $instance->scheduled_local_date->toDateString());
            $this->assertSame('09:00:00', $instance->scheduled_local_time);
            $this->assertSame('2026-08-28 06:00:00', $instance->scheduled_at->format('Y-m-d H:i:s'));
            $this->assertSame(JobDispatchStatus::Pending, $dispatch->status);
            $this->assertSame($instance->id, $dispatch->target_id);
        });
    }

    public function test_zero_total_invoice_materializes_no_reminders(): void
    {
        [$owner, $company] = $this->company();
        $this->rule($company, 'BEFORE_DUE', 5);
        $invoice = $this->completeInvoice($company, $this->invoice($company, $owner));
        $this->tenant($company, function () use ($invoice): void {
            DocumentLine::query()->where('document_id', $invoice->id)->update([
                'item_price' => '0',
                'items_subtotal' => '0',
                'items_total' => '0',
                'grand_subtotal' => '0',
                'tax_amount' => '0',
                'final_line_total' => '0',
            ]);
            $invoice->update(['subtotal' => '0', 'tax_total' => '0', 'total' => '0']);
        });

        app(IssueInvoice::class)->handle(
            $company,
            $owner,
            $invoice->id,
            $invoice->edit_version,
        );

        $this->tenant($company, function (): void {
            $this->assertSame(0, ReminderInstance::query()->count());
            $this->assertSame(0, JobDispatch::query()->count());
        });
    }

    public function test_downtime_marks_before_due_stale_and_sends_only_newest_after_due(): void
    {
        [$owner, $company] = $this->company();
        $this->rule($company, 'BEFORE_DUE', 1, 1);
        $this->rule($company, 'AFTER_DUE', 1, 2);
        $this->rule($company, 'AFTER_DUE', 7, 3);
        $invoice = $this->completeInvoice($company, $this->invoice($company, $owner));
        app(IssueInvoice::class)->handle($company, $owner, $invoice->id, $invoice->edit_version);
        Date::setTestNow('2026-10-10 12:00:00 Europe/Bucharest');
        $instances = $this->tenant(
            $company,
            fn () => ReminderInstance::query()->orderBy('day_offset')->orderBy('relation')->get(),
        );

        foreach ($instances as $instance) {
            $this->tenant($company, fn () => app(PrepareInvoiceReminder::class)->handle($instance->id));
        }

        $this->tenant($company, function (): void {
            $this->assertSame(1, ReminderInstance::query()
                ->where('status', ReminderInstanceStatus::Skipped)->count());
            $this->assertSame(1, ReminderInstance::query()
                ->where('status', ReminderInstanceStatus::Superseded)->count());
            $this->assertSame(1, ReminderInstance::query()
                ->where('status', ReminderInstanceStatus::Claimed)->count());
            $this->assertSame(1, EmailDelivery::query()->count());
        });
    }

    public function test_due_claim_is_atomic_and_duplicate_safe(): void
    {
        [$owner, $company] = $this->company();
        $this->rule($company, 'BEFORE_DUE', 30);
        $invoice = $this->completeInvoice($company, $this->invoice($company, $owner));
        app(IssueInvoice::class)->handle($company, $owner, $invoice->id, $invoice->edit_version);
        Queue::fake();

        $this->assertSame(1, app(ClaimDueJobDispatches::class)->handle());
        $this->assertSame(0, app(ClaimDueJobDispatches::class)->handle());
        Queue::assertPushed(SendInvoiceReminder::class, 1);
        $this->tenant($company, fn () => $this->assertSame(
            JobDispatchStatus::Queued,
            JobDispatch::query()->sole()->status,
        ));
    }

    public function test_cancel_and_reopen_preserve_history_without_replaying_it(): void
    {
        [$owner, $company] = $this->company();
        $this->rule($company, 'BEFORE_DUE', 30);
        $invoice = $this->completeInvoice($company, $this->invoice($company, $owner));
        $this->actingAs($owner);
        $this->post(route('invoices.issue', [$company, $invoice]), ['edit_version' => 1]);
        $this->post(route('invoices.cancel', [$company, $invoice]), [
            'edit_version' => 2, 'confirmed' => true, 'reason' => 'Customer request',
        ])->assertRedirect();
        $this->post(route('invoices.reopen', [$company, $invoice]), [
            'edit_version' => 3, 'confirmed' => true, 'reason' => 'Customer resumed',
        ])->assertRedirect();

        $this->tenant($company, function (): void {
            $instances = ReminderInstance::query()->orderBy('id')->get();
            $this->assertCount(2, $instances);
            $this->assertEqualsCanonicalizing(
                [ReminderInstanceStatus::Suppressed, ReminderInstanceStatus::Skipped],
                $instances->pluck('status')->all(),
            );
            $this->assertNotSame($instances[0]->occurrence_key, $instances[1]->occurrence_key);
        });
    }

    public function test_system_reminder_uses_saved_template_and_tracks_delivery_outcome(): void
    {
        [$owner, $company] = $this->company();
        $this->rule($company, 'BEFORE_DUE', 30);
        $invoice = $this->completeInvoice($company, $this->invoice($company, $owner));
        app(IssueInvoice::class)->handle($company, $owner, $invoice->id, $invoice->edit_version);
        $instanceId = $this->tenant($company, fn (): string => ReminderInstance::query()->sole()->id);
        Queue::fake();

        (new SendInvoiceReminder($company->id, $instanceId))->handle(
            app(TenantContext::class),
            app(PrepareInvoiceReminder::class),
        );
        Queue::assertPushed(SendDocumentDelivery::class, 1);
        $delivery = $this->tenant($company, fn (): EmailDelivery => EmailDelivery::query()->sole());
        $provider = new ReminderRecordingProvider;
        $this->executeDeliveryJob($company->id, $delivery->id, $provider);
        $this->assertCount(1, $provider->deliveries);
        $this->assertStringContainsString(
            $invoice->rendered_number,
            $provider->deliveries[0]->subject,
        );
        $this->tenant($company, function (): void {
            $delivery = EmailDelivery::query()->sole();
            $instance = ReminderInstance::query()->sole();
            $this->assertSame('PAYMENT_REMINDER', $delivery->event_type->value);
            $this->assertNull($delivery->initiated_by_user_id);
            $this->assertSame(ReminderInstanceStatus::Sent, $instance->status);
            $this->assertSame($instance->id, $delivery->reminder_instance_id);
            $audit = AuditEvent::query()->where('action', 'company.invoice.reminder.queued')->sole();
            $this->assertArrayNotHasKey('email', $audit->after);
        });
        $editVersion = $this->tenant($company, fn (): int => $invoice->refresh()->edit_version);
        $this->actingAs($owner)->put(route('invoices.reminders.update', [$company, $invoice]), [
            'edit_version' => $editVersion,
            'rules' => [],
        ])->assertRedirect();
        $this->tenant($company, function (): void {
            $this->assertSame(0, DocumentReminderRule::query()->count());
            $instance = ReminderInstance::query()->sole();
            $this->assertSame(ReminderInstanceStatus::Sent, $instance->status);
            $this->assertNull($instance->document_reminder_rule_id);
        });
    }

    public function test_all_company_roles_can_override_invoice_rules(): void
    {
        [$owner, $company] = $this->company();
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $company->memberships()->create(['user_id' => $admin->id, 'role' => CompanyRole::Admin]);
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);
        $this->rule($company, 'BEFORE_DUE', 5);
        $invoice = $this->completeInvoice($company, $this->invoice($company, $owner));
        $rule = $this->tenant(
            $company,
            fn () => DocumentReminderRule::query()->sole(),
        );

        foreach ([$owner, $admin, $member] as $actor) {
            $this->actingAs($actor)
                ->get(route('invoices.edit', [$company, $invoice]))
                ->assertInertia(fn (Assert $page) => $page
                    ->where('reminders.rules.0.id', $rule->id)
                    ->where('reminders.saveUrl', route(
                        'invoices.reminders.update', [$company, $invoice], false,
                    )));
        }

        $this->actingAs($member)->put(route('invoices.reminders.update', [$company, $invoice]), [
            'edit_version' => $invoice->edit_version,
            'rules' => [[
                'id' => $rule->id,
                'relation' => 'AFTER_DUE',
                'day_offset' => 2,
                'enabled' => true,
            ]],
        ])->assertRedirect()->assertSessionHas('status');
        $this->tenant($company, fn () => $this->assertSame(
            'AFTER_DUE',
            DocumentReminderRule::query()->sole()->relation->value,
        ));

        $editVersion = $this->tenant(
            $company,
            fn (): int => $invoice->refresh()->edit_version,
        );
        $this->assertSame(2, $editVersion);
        $this->actingAs($owner)->put(route('invoices.reminders.update', [$company, $invoice]), [
            'edit_version' => $editVersion,
            'rules' => [[
                'relation' => 'BEFORE_DUE',
                'day_offset' => 9,
                'enabled' => true,
            ]],
        ])->assertRedirect()->assertSessionHas('status');
        $this->tenant($company, function (): void {
            $replacement = DocumentReminderRule::query()->sole();
            $this->assertSame('BEFORE_DUE', $replacement->relation->value);
            $this->assertSame(9, $replacement->day_offset);
        });
    }

    private function rule(Company $company, string $relation, int $offset, int $order = 1): void
    {
        $this->tenant($company, function () use ($relation, $offset, $order): void {
            CompanySetting::query()->firstOrFail()->update(['automation_local_time' => '09:00:00']);
            CompanyReminderRule::query()->create([
                'relation' => $relation,
                'day_offset' => $offset,
                'enabled' => true,
                'display_order' => $order,
            ]);
        });
    }
}

final class ReminderRecordingProvider implements SendsProviderEmail
{
    /** @var list<ProviderDelivery> */
    public array $deliveries = [];

    public function send(ProviderDelivery $delivery): ProviderDeliveryResult
    {
        $this->deliveries[] = $delivery;

        return ProviderDeliveryResult::accepted('reminder-request');
    }
}
