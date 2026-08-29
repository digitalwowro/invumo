<?php

namespace Tests\Feature\Modules\Delivery;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Delivery\Actions\PrepareInvoiceReminder;
use App\Modules\Delivery\Contracts\SendsProviderEmail;
use App\Modules\Delivery\Data\JobDispatchStatus;
use App\Modules\Delivery\Data\ProviderDelivery;
use App\Modules\Delivery\Data\ProviderDeliveryResult;
use App\Modules\Delivery\Data\ReminderInstanceStatus;
use App\Modules\Delivery\Jobs\SendInvoiceReminder;
use App\Modules\Delivery\Models\CompanyReminderRule;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Delivery\Models\JobDispatch;
use App\Modules\Delivery\Models\PublicDocumentLink;
use App\Modules\Delivery\Models\ReminderInstance;
use App\Modules\Documents\Models\DocumentDeliverySetting;
use App\Modules\Invoices\Actions\IssueInvoice;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\DocumentDeliveryTestCase;

final class InvoiceReminderRetryTest extends DocumentDeliveryTestCase
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

    public function test_owner_can_retry_a_corrected_failure_while_member_cannot(): void
    {
        [$owner, $company] = $this->company();
        $member = User::factory()->create();
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);
        $this->tenant($company, function (): void {
            CompanySetting::query()->firstOrFail()->update(['automation_local_time' => '09:00:00']);
            CompanyReminderRule::query()->create([
                'relation' => 'BEFORE_DUE',
                'day_offset' => 30,
                'enabled' => true,
                'display_order' => 1,
            ]);
        });
        $invoice = $this->completeInvoice($company, $this->invoice($company, $owner));
        $this->tenant($company, function () use ($invoice): void {
            DocumentDeliverySetting::query()->where('document_id', $invoice->id)->update([
                'public_access_enabled' => false,
            ]);
        });
        app(IssueInvoice::class)->handle($company, $owner, $invoice->id, $invoice->edit_version);
        $instanceId = $this->tenant($company, fn (): string => ReminderInstance::query()->sole()->id);

        (new SendInvoiceReminder($company->id, $instanceId))->handle(
            app(TenantContext::class),
            app(PrepareInvoiceReminder::class),
        );
        [$dispatchId, $dispatchKey] = $this->tenant($company, function (): array {
            $instance = ReminderInstance::query()->sole();
            $this->assertSame(ReminderInstanceStatus::Failed, $instance->status);
            $dispatch = JobDispatch::query()->sole();
            $this->assertSame(JobDispatchStatus::Completed, $dispatch->status);

            return [$dispatch->id, $dispatch->idempotency_key];
        });
        $this->actingAs($owner)
            ->get(route('company-reminder-rules.index', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->where('failures.0.id', $instanceId)
                ->where('failures.0.invoiceNumber', $invoice->rendered_number)
                ->where('failures.0.failure', 'Secure public access is disabled for this Invoice.'));
        $this->tenant($company, fn () => DocumentDeliverySetting::query()
            ->where('document_id', $invoice->id)->update(['public_access_enabled' => true]));

        $this->actingAs($member)
            ->post(route('invoices.reminders.retry', [$company, $invoice, $instanceId]), [
                'confirmed' => true,
            ])
            ->assertForbidden();
        $this->actingAs($owner)
            ->post(route('invoices.reminders.retry', [$company, $invoice, $instanceId]), [
                'confirmed' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->tenant($company, function () use ($dispatchId, $dispatchKey): void {
            $instance = ReminderInstance::query()->sole();
            $dispatch = JobDispatch::query()->sole();
            $this->assertSame(ReminderInstanceStatus::Pending, $instance->status);
            $this->assertSame(JobDispatchStatus::Pending, $dispatch->status);
            $this->assertSame($dispatchId, $dispatch->id);
            $this->assertSame($dispatchKey, $dispatch->idempotency_key);
            $audit = AuditEvent::query()
                ->where('action', 'company.invoice.reminder.retry_queued')->sole();
            $this->assertEqualsCanonicalizing([
                'reminder_instance_id' => $instance->id,
                'failure_category' => 'public_access_disabled',
            ], $audit->after);
        });
    }

    public function test_retry_recomposes_a_fresh_delivery_and_renews_an_expired_link(): void
    {
        [$owner, $company] = $this->company();
        $this->tenant($company, function (): void {
            CompanySetting::query()->firstOrFail()->update(['automation_local_time' => '09:00:00']);
            CompanyReminderRule::query()->create([
                'relation' => 'AFTER_DUE', 'day_offset' => 1,
                'enabled' => true, 'display_order' => 1,
            ]);
        });
        $invoice = $this->completeInvoice($company, $this->invoice($company, $owner));
        Date::setTestNow('2026-09-29 12:00:00 Europe/Bucharest');
        app(IssueInvoice::class)->handle($company, $owner, $invoice->id, $invoice->edit_version);
        $instanceId = $this->tenant($company, fn (): string => ReminderInstance::query()->sole()->id);
        (new SendInvoiceReminder($company->id, $instanceId))->handle(
            app(TenantContext::class),
            app(PrepareInvoiceReminder::class),
        );
        $firstDelivery = $this->tenant($company, fn (): EmailDelivery => EmailDelivery::query()->sole());
        $this->executeDeliveryJob(
            $company->id,
            $firstDelivery->id,
            new AmbiguousReminderProvider,
        );
        $this->tenant($company, fn () => $this->assertSame(
            ReminderInstanceStatus::Failed,
            ReminderInstance::query()->sole()->status,
        ));
        Date::setTestNow('2026-11-01 12:00:00 Europe/Bucharest');

        $this->actingAs($owner)
            ->post(route('invoices.reminders.retry', [$company, $invoice, $instanceId]), [
                'confirmed' => true,
            ])->assertRedirect();
        (new SendInvoiceReminder($company->id, $instanceId))->handle(
            app(TenantContext::class),
            app(PrepareInvoiceReminder::class),
        );

        $this->tenant($company, function () use ($firstDelivery, $instanceId): void {
            $deliveries = EmailDelivery::query()->orderBy('id')->get();
            $this->assertCount(2, $deliveries);
            $this->assertNotSame($firstDelivery->id, $deliveries->last()->id);
            $this->assertTrue($deliveries->every(
                fn (EmailDelivery $delivery): bool => $delivery->reminder_instance_id === $instanceId,
            ));
            $this->assertSame(2, PublicDocumentLink::query()->count());
        });
    }
}

final class AmbiguousReminderProvider implements SendsProviderEmail
{
    public function send(ProviderDelivery $delivery): ProviderDeliveryResult
    {
        return ProviderDeliveryResult::unknown('The provider outcome is unknown.');
    }
}
