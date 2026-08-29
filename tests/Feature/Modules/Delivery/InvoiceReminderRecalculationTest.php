<?php

namespace Tests\Feature\Modules\Delivery;

use App\Foundation\Tenancy\TenantContext;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Delivery\Actions\InvoiceReminderSchedule;
use App\Modules\Delivery\Data\ReminderInstanceStatus;
use App\Modules\Delivery\Models\CompanyReminderRule;
use App\Modules\Delivery\Models\DocumentReminderRule;
use App\Modules\Delivery\Models\ReminderInstance;
use App\Modules\Documents\Models\Document;
use App\Modules\Invoices\Actions\IssueInvoice;
use App\Modules\Invoices\Models\Invoice;
use Illuminate\Support\Facades\Date;
use Tests\Support\DocumentDeliveryTestCase;

final class InvoiceReminderRecalculationTest extends DocumentDeliveryTestCase
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

    public function test_editing_timing_materializes_a_new_occurrence(): void
    {
        [$owner, $company] = $this->company();
        $this->companyRule($company);
        $invoice = $this->completeInvoice($company, $this->invoice($company, $owner));
        app(IssueInvoice::class)->handle($company, $owner, $invoice->id, $invoice->edit_version);
        $rule = $this->tenant($company, fn (): DocumentReminderRule => DocumentReminderRule::query()->sole());

        $this->actingAs($owner)->put(
            route('invoices.reminders.update', [$company, $invoice]),
            [
                'edit_version' => 2,
                'rules' => [[
                    'id' => $rule->id,
                    'relation' => 'BEFORE_DUE',
                    'day_offset' => 2,
                    'enabled' => true,
                ]],
            ],
        )->assertRedirect();

        $this->tenant($company, function (): void {
            $instances = ReminderInstance::query()->orderBy('id')->get();
            $this->assertCount(2, $instances);
            $this->assertSame(ReminderInstanceStatus::Skipped, $instances[0]->status);
            $this->assertSame(ReminderInstanceStatus::Pending, $instances[1]->status);
            $this->assertNotSame($instances[0]->occurrence_key, $instances[1]->occurrence_key);
        });
    }

    public function test_each_unsent_resume_cycle_gets_a_distinct_occurrence(): void
    {
        [$owner, $company] = $this->company();
        $this->companyRule($company);
        $invoice = $this->completeInvoice($company, $this->invoice($company, $owner));
        app(IssueInvoice::class)->handle($company, $owner, $invoice->id, $invoice->edit_version);

        app(TenantContext::class)->runAsSystem($company->id, function () use ($invoice): void {
            $document = Document::query()->whereKey($invoice->id)->firstOrFail();
            $invoice = Invoice::query()->whereKey($invoice->id)->firstOrFail();
            $settings = CompanySetting::query()->firstOrFail();
            $schedule = app(InvoiceReminderSchedule::class);

            $schedule->suppress($document, 'nothing_outstanding');
            $schedule->resume($document, $invoice, $settings);
            $schedule->suppress($document, 'nothing_outstanding');
            $schedule->resume($document, $invoice, $settings);
        });

        $this->tenant($company, function (): void {
            $instances = ReminderInstance::query()->orderBy('id')->get();
            $this->assertCount(3, $instances);
            $this->assertCount(2, $instances->where('status', ReminderInstanceStatus::Suppressed));
            $this->assertCount(1, $instances->where('status', ReminderInstanceStatus::Pending));
            $this->assertCount(3, $instances->pluck('occurrence_key')->unique());
        });
    }

    private function companyRule(Company $company): void
    {
        $this->tenant($company, fn () => CompanyReminderRule::query()->create([
            'relation' => 'AFTER_DUE',
            'day_offset' => 30,
            'enabled' => true,
            'display_order' => 1,
        ]));
    }
}
