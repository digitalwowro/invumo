<?php

namespace Tests\Feature\Modules\Recurring;

use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerContact;
use App\Modules\Customers\Models\CustomerDeliveryRecipient;
use App\Modules\Delivery\Contracts\SendsProviderEmail;
use App\Modules\Delivery\Data\EmailDeliveryState;
use App\Modules\Delivery\Data\ProviderDelivery;
use App\Modules\Delivery\Data\ProviderDeliveryResult;
use App\Modules\Delivery\Jobs\SendDocumentDelivery;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Delivery\Models\JobDispatch;
use App\Modules\Documents\Models\Document;
use App\Modules\Recurring\Actions\GenerateDueRecurringInvoices;
use App\Modules\Recurring\Actions\SyncRecurringDispatch;
use App\Modules\Recurring\Models\RecurringOccurrence;
use App\Modules\Recurring\Models\RecurringTemplate;
use App\Modules\Recurring\Models\RecurringTemplateCustomerValue;
use App\Modules\Recurring\Models\RecurringTemplateLine;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\DocumentDeliveryTestCase;

final class RecurringAutomaticDeliveryTest extends DocumentDeliveryTestCase
{
    public function test_inherited_currency_change_latches_and_manual_acceptance_confirms_it(): void
    {
        CarbonImmutable::setTestNow('2026-08-29 10:00:00 UTC');
        [$owner, $company] = $this->company('Recurring Delivery SRL');
        [$template, $customer, $dispatch] = $this->scheduled(
            $company,
            maximumOccurrences: 2,
        );
        Queue::fake();

        $this->assertSame(1, app(GenerateDueRecurringInvoices::class)
            ->handle($company->id, $dispatch->id, 1));
        [$firstDelivery, $nextDispatch] = $this->tenant($company, function () use (
            $template,
            $customer,
        ): array {
            $delivery = EmailDelivery::query()->sole();
            $occurrence = RecurringOccurrence::query()->sole();
            $template->refresh();
            $this->assertTrue($delivery->recurring_automatic);
            $this->assertNull($delivery->initiated_by_user_id);
            $this->assertTrue($occurrence->automatic_email_requested);
            $this->assertNull($occurrence->automatic_delivery_suppression_reason);
            $this->assertSame('RON', $template->last_confirmed_delivery_currency);
            $eur = CompanyCurrency::query()->create([
                'currency_code' => 'EUR', 'currency_precision' => 2,
                'is_default' => false, 'active' => true,
            ]);
            $customer->update(['currency_id' => $eur->id]);

            return [
                $delivery,
                app(SyncRecurringDispatch::class)->handle($template),
            ];
        });
        Queue::assertPushed(SendDocumentDelivery::class, 1);

        CarbonImmutable::setTestNow('2026-09-05 10:00:00 UTC');
        $this->assertSame(1, app(GenerateDueRecurringInvoices::class)
            ->handle($company->id, $nextDispatch->id, 1));
        $affected = $this->tenant($company, function () use ($template): Document {
            $template->refresh();
            $occurrences = RecurringOccurrence::query()->orderBy('logical_ordinal')->get();
            $this->assertCount(2, $occurrences);
            $this->assertSame(
                'CURRENCY_REVIEW_REQUIRED',
                $occurrences->last()->automatic_delivery_suppression_reason,
            );
            $this->assertTrue($template->currency_review_required);
            $this->assertSame('EUR', $template->currency_review_currency);
            $this->assertSame(1, EmailDelivery::query()->count());

            return Document::query()->whereKey($occurrences->last()->invoice_id)->firstOrFail();
        });

        $blockedProvider = new RecurringRecordingProvider;
        $this->executeDeliveryJob($company->id, $firstDelivery->id, $blockedProvider);
        $this->assertCount(0, $blockedProvider->deliveries);
        $this->tenant($company, fn () => $this->assertSame(
            EmailDeliveryState::Rejected,
            $firstDelivery->refresh()->dispatch_state,
        ));
        $this->actingAs($owner)
            ->get(route('invoices.edit', [$company, $firstDelivery->document_id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('directDelivery.history.0.retryUrl', null));

        Queue::fake();
        $this->actingAs($owner)->post(
            route('invoices.deliveries.store', [$company, $affected]),
            $this->deliveryPayload($affected),
        )->assertRedirect()->assertSessionDoesntHaveErrors();
        $manual = $this->tenant($company, fn (): EmailDelivery => EmailDelivery::query()
            ->where('document_id', $affected->id)->where('recurring_automatic', false)->sole());
        $acceptedProvider = new RecurringRecordingProvider;
        $this->executeDeliveryJob($company->id, $manual->id, $acceptedProvider);

        $this->assertCount(1, $acceptedProvider->deliveries);
        $this->tenant($company, function () use ($template): void {
            $template->refresh();
            $this->assertSame('COMPLETED', $template->state->value);
            $this->assertFalse($template->currency_review_required);
            $this->assertSame('EUR', $template->last_confirmed_delivery_currency);
            $this->assertNull($template->currency_review_currency);
        });

    }

    public function test_explicit_currency_bypasses_inherited_currency_comparison(): void
    {
        CarbonImmutable::setTestNow('2026-08-29 10:00:00 UTC');
        [, $company] = $this->company('Explicit Currency SRL');
        [$template, , $dispatch] = $this->scheduled($company, explicitCurrency: true);
        Queue::fake();

        $this->assertSame(1, app(GenerateDueRecurringInvoices::class)
            ->handle($company->id, $dispatch->id, 1));
        $delivery = $this->tenant($company, function () use ($template): EmailDelivery {
            $template->refresh();
            $occurrence = RecurringOccurrence::query()->sole();
            $this->assertFalse($occurrence->currency_inherited);
            $this->assertNull($occurrence->automatic_delivery_suppression_reason);
            $this->assertTrue(EmailDelivery::query()->sole()->recurring_automatic);
            $this->assertNull($template->last_confirmed_delivery_currency);
            $this->assertFalse($template->currency_review_required);

            return EmailDelivery::query()->sole();
        });

        $provider = new RecurringRecordingProvider;
        $this->executeDeliveryJob($company->id, $delivery->id, $provider);
        $this->assertCount(1, $provider->deliveries);

    }

    public function test_database_rejects_invalid_automatic_delivery_state(): void
    {
        CarbonImmutable::setTestNow('2026-08-29 10:00:00 UTC');
        [, $company] = $this->company('Recurring Constraints SRL');
        [$template, , $dispatch] = $this->scheduled($company);
        Queue::fake();
        app(GenerateDueRecurringInvoices::class)->handle($company->id, $dispatch->id, 1);

        foreach ([
            fn () => RecurringOccurrence::query()->sole()->update([
                'automatic_email_requested' => false,
                'automatic_delivery_suppression_reason' => 'CURRENCY_REVIEW_REQUIRED',
            ]),
            fn () => $template->update([
                'currency_review_required' => true,
                'currency_review_currency' => null,
                'currency_review_detected_at' => now(),
            ]),
            fn () => EmailDelivery::query()->sole()->update([
                'recurring_automatic' => false,
            ]),
        ] as $invalidWrite) {
            try {
                $this->tenant($company, $invalidWrite);
                $this->fail('Invalid automatic-delivery state must be rejected.');
            } catch (QueryException $exception) {
                $this->assertContains($exception->errorInfo[0], ['23001', '23514']);
            }
        }
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    /** @return array{RecurringTemplate, Customer, JobDispatch} */
    private function scheduled(
        Company $company,
        bool $explicitCurrency = false,
        ?int $maximumOccurrences = null,
    ): array {
        return $this->tenant($company, function () use (
            $explicitCurrency,
            $maximumOccurrences,
        ): array {
            CompanySetting::query()->firstOrFail()->update([
                'timezone' => 'UTC', 'automation_local_time' => '09:00',
                'default_document_language' => 'en', 'default_payment_term_days' => 14,
                'public_links_enabled_by_default' => true,
            ]);
            $customer = Customer::query()->create([
                'type' => 'COMPANY', 'legal_name' => 'Recurring Customer SRL',
            ]);
            $contact = CustomerContact::query()->create([
                'customer_id' => $customer->id, 'name' => 'Billing',
                'email' => 'billing@example.com', 'display_order' => 1,
            ]);
            CustomerDeliveryRecipient::query()->create([
                'customer_id' => $customer->id, 'role' => 'TO',
                'contact_id' => $contact->id, 'display_order' => 1,
            ]);
            $template = RecurringTemplate::query()->create([
                'client_creation_key' => (string) Str::uuid7(),
                'internal_name' => 'Automatic monthly service',
                'customer_id' => $customer->id,
                'automatic_email_enabled' => true,
            ]);
            RecurringTemplateLine::query()->create([
                'recurring_template_id' => $template->id, 'position' => 1,
                'description' => 'Service', 'item_price' => '100', 'quantity' => '1',
                'period_unit' => 'NONE', 'discount_percentage' => '0',
                'tax_percentage' => '0',
            ]);

            if ($explicitCurrency) {
                $eur = CompanyCurrency::query()->create([
                    'currency_code' => 'EUR', 'currency_precision' => 2,
                    'is_default' => false, 'active' => true,
                ]);
                RecurringTemplateCustomerValue::query()->create([
                    'recurring_template_id' => $template->id,
                    'explicit_fields' => ['currency'],
                    'currency_id' => $eur->id,
                    'currency_code' => 'EUR',
                    'currency_precision' => 2,
                ]);
            }

            $template->update([
                'state' => 'ACTIVE', 'recurrence_kind' => 'WEEKLY',
                'start_date' => '2026-08-29', 'next_occurrence_date' => '2026-08-29',
                'schedule_timezone' => 'UTC', 'schedule_local_time' => '09:00',
                'next_run_at' => '2026-08-29 09:00:00+00', 'activated_at' => now(),
                'next_logical_ordinal' => 0, 'successful_occurrence_count' => 0,
                'maximum_occurrence_count' => $maximumOccurrences,
            ]);

            return [$template, $customer, app(SyncRecurringDispatch::class)->handle($template)];
        });
    }
}

final class RecurringRecordingProvider implements SendsProviderEmail
{
    /** @var list<ProviderDelivery> */
    public array $deliveries = [];

    public function send(ProviderDelivery $delivery): ProviderDeliveryResult
    {
        $this->deliveries[] = $delivery;

        return ProviderDeliveryResult::accepted('recurring-delivery-test');
    }
}
