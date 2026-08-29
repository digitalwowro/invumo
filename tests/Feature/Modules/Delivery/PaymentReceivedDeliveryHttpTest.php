<?php

namespace Tests\Feature\Modules\Delivery;

use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Delivery\Contracts\SendsProviderEmail;
use App\Modules\Delivery\Data\EmailDeliveryState;
use App\Modules\Delivery\Data\EmailTemplateEvent;
use App\Modules\Delivery\Data\ProviderDelivery;
use App\Modules\Delivery\Data\ProviderDeliveryResult;
use App\Modules\Delivery\Jobs\SendDocumentDelivery;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Delivery\Models\EmailDeliveryRecipient;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentDeliveryRecipient;
use App\Modules\Invoices\Actions\IssueInvoice;
use App\Modules\Transactions\Models\InvoiceTransaction;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\DocumentDeliveryTestCase;

final class PaymentReceivedDeliveryHttpTest extends DocumentDeliveryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-08-29 12:00:00 Europe/Bucharest');
    }

    protected function tearDown(): void
    {
        Date::setTestNow();
        parent::tearDown();
    }

    public function test_payment_creation_never_sends_and_explicit_action_queues_one_receipt(): void
    {
        [$owner, $company] = $this->company();
        $owner->update(['language_code' => 'ro']);
        $invoice = $this->issuedInvoice($company, $owner, language: 'ro');
        Queue::fake();
        $this->actingAs($owner)->post(
            route('invoice-transactions.store', [$company, $invoice]),
            $this->paymentPayload('50'),
        )->assertRedirect();
        $payment = $this->payment($company);

        $this->assertSame(0, $this->tenant(
            $company,
            fn (): int => EmailDelivery::query()->count(),
        ));
        Queue::assertNothingPushed();
        $this->get(route('invoices.edit', [$company, $invoice]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('transactions.items.0.receipt.count', 0)
                ->where('transactions.items.0.receipt.latestState', null)
                ->where('transactions.items.0.receipt.mayHaveBeenSent', false)
                ->where('translations.transactions.receipt.send', 'Trimite confirmarea'));

        $payload = $this->receiptPayload($payment);
        $this->post(
            route('invoice-transactions.payment-received.store', [$company, $invoice, $payment]),
            $payload,
        )->assertRedirect()->assertSessionHas(
            'status',
            'Emailul de înregistrare a plății a fost pus în coadă.',
        );
        $this->post(
            route('invoice-transactions.payment-received.store', [$company, $invoice, $payment]),
            $payload,
        )->assertRedirect();
        $this->post(
            route('invoice-transactions.payment-received.store', [$company, $invoice, $payment]),
            $this->receiptPayload($payment),
        )->assertSessionHasErrors('delivery');

        $this->tenant($company, function () use ($payment): void {
            $delivery = EmailDelivery::query()->sole();
            $audit = AuditEvent::query()
                ->where('action', 'company.invoice.payment_received.queued')->sole();
            $serialized = json_encode($audit->toArray(), JSON_THROW_ON_ERROR);

            $this->assertSame(EmailTemplateEvent::PaymentReceived, $delivery->event_type);
            $this->assertSame($payment->id, $delivery->invoice_transaction_id);
            $this->assertSame(EmailDeliveryState::Queued, $delivery->dispatch_state);
            $this->assertStringContainsString('50,00 RON', (string) $delivery->body);
            $this->assertStringContainsString('29 aug. 2026', (string) $delivery->body);
            $this->assertStringContainsString('/i/', (string) $delivery->button_url);
            $this->assertSame('ana@example.com', EmailDeliveryRecipient::query()->sole()->email);
            $this->assertSame(1, $audit->after['recipient_count']);
            $this->assertStringNotContainsString('ana@example.com', $serialized);
            $this->assertStringNotContainsString('50,00', $serialized);
        });
        Queue::assertPushed(SendDocumentDelivery::class, 1);

        $this->get(route('invoices.edit', [$company, $invoice]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('transactions.items.0.receipt.count', 1)
                ->where('transactions.items.0.receipt.latestState', 'QUEUED')
                ->where('directDelivery.history.0.eventType', 'PAYMENT_RECEIVED'));

        $provider = new PaymentReceivedRecordingProvider;
        $delivery = $this->tenant(
            $company,
            fn (): EmailDelivery => EmailDelivery::query()->sole(),
        );
        $this->executeDeliveryJob($company->id, $delivery->id, $provider);

        $this->assertCount(1, $provider->deliveries);
        $this->assertStringContainsString('50,00 RON', $provider->deliveries[0]->textBody);
        $this->tenant($company, fn () => $this->assertSame(
            EmailDeliveryState::Accepted,
            EmailDelivery::query()->sole()->dispatch_state,
        ));
    }

    public function test_all_company_roles_may_send_but_cross_company_and_non_payments_fail_closed(): void
    {
        [$owner, $company] = $this->company();
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $company->memberships()->create([
            'user_id' => $admin->id,
            'role' => CompanyRole::Admin,
        ]);
        $company->memberships()->create([
            'user_id' => $member->id,
            'role' => CompanyRole::Member,
        ]);
        $invoice = $this->issuedInvoice($company, $owner);
        $this->actingAs($owner)->post(
            route('invoice-transactions.store', [$company, $invoice]),
            $this->paymentPayload('60'),
        );
        $payment = $this->payment($company);
        Queue::fake();

        $this->actingAs($member)->post(
            route('invoice-transactions.payment-received.store', [$company, $invoice, $payment]),
            $this->receiptPayload($payment),
        )->assertRedirect();
        $this->tenant($company, fn () => EmailDelivery::query()->update([
            'dispatch_state' => EmailDeliveryState::Rejected,
            'failure_category' => 'test_rejection',
            'failure_summary' => 'Rejected in test.',
            'failed_at' => now(),
        ]));
        $this->actingAs($admin)->post(
            route('invoice-transactions.payment-received.store', [$company, $invoice, $payment]),
            $this->receiptPayload($payment),
        )->assertRedirect();
        $this->tenant($company, fn () => EmailDelivery::query()
            ->where('dispatch_state', EmailDeliveryState::Queued)
            ->update([
                'dispatch_state' => EmailDeliveryState::Rejected,
                'failure_category' => 'test_rejection',
                'failure_summary' => 'Rejected in test.',
                'failed_at' => now(),
            ]));
        $this->actingAs($owner)->post(
            route('invoice-transactions.store', [$company, $invoice]),
            $this->paymentPayload('10', kind: 'REFUND'),
        );
        $refund = $this->tenant(
            $company,
            fn (): InvoiceTransaction => InvoiceTransaction::query()->where('kind', 'REFUND')->sole(),
        );
        $this->post(
            route('invoice-transactions.payment-received.store', [$company, $invoice, $refund]),
            $this->receiptPayload($refund),
        )->assertSessionHasErrors('delivery');

        [$otherOwner, $other] = $this->company('Other Receipt SRL');
        $this->actingAs($otherOwner)->post(
            route('invoice-transactions.payment-received.store', [$other, $invoice, $payment]),
            $this->receiptPayload($payment),
        )->assertNotFound();
        $this->assertSame(2, $this->tenant(
            $company,
            fn (): int => EmailDelivery::query()->count(),
        ));
    }

    public function test_stale_payment_and_missing_recipients_are_rejected(): void
    {
        [$owner, $company] = $this->company();
        $invoice = $this->issuedInvoice($company, $owner);
        $this->actingAs($owner)->post(
            route('invoice-transactions.store', [$company, $invoice]),
            $this->paymentPayload('50'),
        );
        $payment = $this->payment($company);
        Queue::fake();
        $stale = $this->receiptPayload($payment);
        $stale['transaction_edit_version'] = 2;

        $this->post(
            route('invoice-transactions.payment-received.store', [$company, $invoice, $payment]),
            $stale,
        )->assertSessionHasErrors('delivery');
        $this->tenant($company, fn () => DocumentDeliveryRecipient::query()->delete());
        $this->post(
            route('invoice-transactions.payment-received.store', [$company, $invoice, $payment]),
            $this->receiptPayload($payment),
        )->assertSessionHasErrors('delivery');

        $this->assertSame(0, $this->tenant(
            $company,
            fn (): int => EmailDelivery::query()->count(),
        ));
        Queue::assertNothingPushed();
    }

    /** @return array<string, mixed> */
    private function paymentPayload(string $amount, string $kind = 'PAYMENT'): array
    {
        return [
            'kind' => $kind,
            'adjustment_direction' => null,
            'amount' => $amount,
            'transaction_date' => '2026-08-29',
            'payment_method' => 'Bank transfer',
            'reference' => null,
            'notes' => null,
            'adjustment_reason' => null,
            'mutation_key' => (string) Str::uuid7(),
            'edit_version' => null,
            'confirmed' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function receiptPayload(InvoiceTransaction $payment): array
    {
        return [
            'delivery_key' => (string) Str::uuid7(),
            'transaction_edit_version' => $payment->edit_version,
            'confirmed' => true,
        ];
    }

    private function issuedInvoice(
        Company $company,
        User $owner,
        string $language = 'en',
    ): Document {
        $invoice = $this->completeInvoice($company, $this->invoice($company, $owner));
        $this->tenant($company, fn () => $invoice->update(['document_language' => $language]));

        return app(IssueInvoice::class)->handle(
            $company,
            $owner,
            $invoice->id,
            $invoice->edit_version,
        );
    }

    private function payment(Company $company): InvoiceTransaction
    {
        return $this->tenant(
            $company,
            fn (): InvoiceTransaction => InvoiceTransaction::query()->where('kind', 'PAYMENT')->sole(),
        );
    }
}

final class PaymentReceivedRecordingProvider implements SendsProviderEmail
{
    /** @var list<ProviderDelivery> */
    public array $deliveries = [];

    public function send(ProviderDelivery $delivery): ProviderDeliveryResult
    {
        $this->deliveries[] = $delivery;

        return ProviderDeliveryResult::accepted('payment-received-test');
    }
}
