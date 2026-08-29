<?php

namespace Tests\Feature\Modules\Delivery;

use App\Foundation\Tenancy\TenantContext;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Delivery\Actions\InvoiceReminderSchedule;
use App\Modules\Delivery\Actions\PrepareInvoiceReminder;
use App\Modules\Delivery\Contracts\SendsProviderEmail;
use App\Modules\Delivery\Data\ProviderDelivery;
use App\Modules\Delivery\Data\ProviderDeliveryResult;
use App\Modules\Delivery\Data\ReminderInstanceStatus;
use App\Modules\Delivery\Jobs\SendInvoiceReminder;
use App\Modules\Delivery\Models\CompanyReminderRule;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Delivery\Models\ReminderInstance;
use App\Modules\Documents\Models\Document;
use App\Modules\Invoices\Actions\IssueInvoice;
use App\Modules\Invoices\Models\Invoice;
use Illuminate\Support\Facades\Date;
use Tests\Support\DocumentDeliveryTestCase;

final class InvoiceReminderHistoryTest extends DocumentDeliveryTestCase
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

    public function test_completed_rule_is_not_replayed_during_recalculation(): void
    {
        [$owner, $company] = $this->company();
        $this->tenant($company, fn () => CompanyReminderRule::query()->create([
            'relation' => 'BEFORE_DUE',
            'day_offset' => 30,
            'enabled' => true,
            'display_order' => 1,
        ]));
        $document = $this->completeInvoice($company, $this->invoice($company, $owner));
        app(IssueInvoice::class)->handle(
            $company,
            $owner,
            $document->id,
            $document->edit_version,
        );
        $instanceId = $this->tenant(
            $company,
            fn (): string => ReminderInstance::query()->sole()->id,
        );
        (new SendInvoiceReminder($company->id, $instanceId))->handle(
            app(TenantContext::class),
            app(PrepareInvoiceReminder::class),
        );
        $delivery = $this->tenant(
            $company,
            fn (): EmailDelivery => EmailDelivery::query()->sole(),
        );
        $this->executeDeliveryJob($company->id, $delivery->id, new AcceptedReminderProvider);

        $this->tenant($company, function () use ($document): void {
            $settings = CompanySetting::query()->lockForUpdate()->firstOrFail();
            $lockedDocument = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            $invoice = Invoice::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            app(InvoiceReminderSchedule::class)->recalculatePending(
                $lockedDocument,
                $invoice,
                $settings,
            );

            $this->assertSame(1, ReminderInstance::query()->count());
            $this->assertSame(
                ReminderInstanceStatus::Sent,
                ReminderInstance::query()->sole()->status,
            );
        });
    }
}

final class AcceptedReminderProvider implements SendsProviderEmail
{
    public function send(ProviderDelivery $delivery): ProviderDeliveryResult
    {
        return ProviderDeliveryResult::accepted('accepted-reminder');
    }
}
