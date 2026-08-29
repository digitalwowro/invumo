<?php

namespace Tests\Feature\Modules\Delivery;

use App\Foundation\Delivery\EmailAttachmentMode;
use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Delivery\Data\EmailDeliveryState;
use App\Modules\Delivery\Data\EmailTemplateEvent;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use App\Modules\Invoices\Actions\IssueInvoice;
use App\Modules\Transactions\Models\InvoiceTransaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\DocumentDeliveryTestCase;

final class PaymentReceivedDeliveryDatabaseTest extends DocumentDeliveryTestCase
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

    public function test_database_requires_a_same_invoice_payment_reference(): void
    {
        [$owner, $company] = $this->company();
        $invoice = $this->invoiceWithPayment($company, $owner);
        $payment = $this->payment($company);
        Queue::fake();
        $this->actingAs($owner)->post(
            route('invoice-transactions.payment-received.store', [$company, $invoice, $payment]),
            $this->receiptPayload($payment),
        )->assertRedirect();

        $attributes = $this->tenant($company, function () use ($invoice, $payment): array {
            $delivery = EmailDelivery::query()->sole();

            return [
                'document_id' => $invoice->id,
                'public_document_link_id' => $delivery->public_document_link_id,
                'document_kind' => DocumentKind::Invoice,
                'event_type' => EmailTemplateEvent::PaymentReceived,
                'delivery_key' => (string) Str::uuid7(),
                'document_edit_version' => $invoice->edit_version,
                'invoice_transaction_edit_version' => $payment->edit_version,
                'language_code' => 'en',
                'subject' => 'Payment received',
                'body' => 'Payment received.',
                'button_label' => 'View invoice',
                'button_url' => $delivery->button_url,
                'attachment_mode' => EmailAttachmentMode::SecureLinkOnly,
                'provider_name' => 'ZEPTOMAIL',
                'dispatch_state' => EmailDeliveryState::Rejected,
                'failure_category' => 'test_rejection',
                'failure_summary' => 'Rejected in test.',
                'failed_at' => now(),
            ];
        });

        $transactionId = $payment->id;
        foreach ([
            [...$attributes, 'invoice_transaction_id' => null],
            [
                ...$attributes,
                'delivery_key' => (string) Str::uuid7(),
                'event_type' => EmailTemplateEvent::InvoiceSent,
                'invoice_transaction_id' => $transactionId,
            ],
            [
                ...$attributes,
                'delivery_key' => (string) Str::uuid7(),
                'invoice_transaction_id' => $transactionId,
                'invoice_transaction_edit_version' => $payment->edit_version + 1,
            ],
        ] as $invalid) {
            try {
                $this->tenant($company, function () use ($invalid): void {
                    EmailDelivery::query()->create($invalid);
                });
                $this->fail('An invalid payment-received reference was accepted.');
            } catch (QueryException $exception) {
                $this->assertSame('23514', $exception->errorInfo[0] ?? null);
            }
        }

        try {
            $this->tenant($company, fn () => EmailDelivery::query()->update([
                'invoice_transaction_edit_version' => $payment->edit_version + 1,
            ]));
            $this->fail('An immutable payment-received version was rewritten.');
        } catch (QueryException $exception) {
            $this->assertSame('23001', $exception->errorInfo[0] ?? null);
        }
    }

    public function test_deleting_a_sent_payment_clears_only_its_delivery_reference(): void
    {
        [$owner, $company] = $this->company();
        $invoice = $this->invoiceWithPayment($company, $owner);
        $payment = $this->payment($company);
        Queue::fake();
        $this->actingAs($owner)->post(
            route('invoice-transactions.payment-received.store', [$company, $invoice, $payment]),
            $this->receiptPayload($payment),
        );
        $this->tenant($company, fn () => EmailDelivery::query()->update([
            'dispatch_state' => EmailDeliveryState::Accepted,
            'accepted_at' => now(),
        ]));

        $this->get(route('invoices.edit', [$company, $invoice]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('transactions.items.0.receipt.mayHaveBeenSent', true));
        $this->delete(
            route('invoice-transactions.destroy', [$company, $invoice, $payment]),
            [
                'edit_version' => $payment->edit_version,
                'mutation_key' => (string) Str::uuid7(),
                'confirmed' => true,
            ],
        )->assertRedirect();

        $this->tenant($company, function (): void {
            $delivery = EmailDelivery::query()->sole();
            $this->assertNull($delivery->invoice_transaction_id);
            $this->assertSame(1, $delivery->invoice_transaction_edit_version);
            $this->assertNotNull($delivery->subject);
            $this->assertNotNull($delivery->body);
            $this->assertSame(EmailTemplateEvent::PaymentReceived, $delivery->event_type);
            $this->assertSame(0, InvoiceTransaction::query()->count());
        });
        $this->get(route('invoices.edit', [$company, $invoice]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('transactions.items', [])
                ->where('directDelivery.history.0.eventType', 'PAYMENT_RECEIVED'));
    }

    public function test_changing_a_sent_payment_kind_detaches_history_without_rewriting_it(): void
    {
        [$owner, $company] = $this->company();
        $invoice = $this->invoiceWithPayment($company, $owner);
        $payment = $this->payment($company);
        Queue::fake();
        $this->actingAs($owner)->post(
            route('invoice-transactions.payment-received.store', [$company, $invoice, $payment]),
            $this->receiptPayload($payment),
        );
        $this->tenant($company, fn () => EmailDelivery::query()->update([
            'dispatch_state' => EmailDeliveryState::Accepted,
            'accepted_at' => now(),
        ]));

        try {
            $this->tenant($company, fn () => DB::connection(
                config('database.tenant_connection'),
            )->transaction(fn () => InvoiceTransaction::query()->whereKey($payment->id)->update([
                'kind' => 'ADJUSTMENT',
                'adjustment_direction' => 'INCREASE_PAID',
                'adjustment_reason' => 'Direct invalid rewrite',
            ])));
            $this->fail('The database accepted a non-Payment receipt reference.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->errorInfo[0] ?? null);
        }

        $this->actingAs($owner)->patch(
            route('invoice-transactions.update', [$company, $invoice, $payment]),
            [
                'kind' => 'ADJUSTMENT', 'adjustment_direction' => 'INCREASE_PAID',
                'amount' => '50', 'transaction_date' => '2026-08-29',
                'payment_method' => null, 'reference' => null, 'notes' => null,
                'adjustment_reason' => 'Corrected classification',
                'mutation_key' => (string) Str::uuid7(), 'edit_version' => 1,
                'confirmed' => true,
            ],
        )->assertRedirect();

        $this->tenant($company, function (): void {
            $delivery = EmailDelivery::query()->sole();
            $this->assertNull($delivery->invoice_transaction_id);
            $this->assertSame(1, $delivery->invoice_transaction_edit_version);
            $this->assertNotNull($delivery->subject);
            $this->assertSame(EmailTemplateEvent::PaymentReceived, $delivery->event_type);
            $this->assertSame('ADJUSTMENT', InvoiceTransaction::query()->sole()->kind->value);
        });
    }

    public function test_forced_rls_hides_payment_received_history_from_other_companies(): void
    {
        [$owner, $company] = $this->company();
        [, $other] = $this->company('Other Receipt RLS SRL');
        $invoice = $this->invoiceWithPayment($company, $owner);
        $payment = $this->payment($company);
        Queue::fake();
        $this->actingAs($owner)->post(
            route('invoice-transactions.payment-received.store', [$company, $invoice, $payment]),
            $this->receiptPayload($payment),
        );

        $this->assertSame(0, $this->tenant(
            $other,
            fn (): int => EmailDelivery::query()->count(),
        ));
    }

    private function invoiceWithPayment(Company $company, User $owner): Document
    {
        $invoice = $this->completeInvoice($company, $this->invoice($company, $owner));
        $invoice = app(IssueInvoice::class)->handle(
            $company,
            $owner,
            $invoice->id,
            $invoice->edit_version,
        );
        $this->actingAs($owner)->post(
            route('invoice-transactions.store', [$company, $invoice]),
            [
                'kind' => 'PAYMENT', 'adjustment_direction' => null, 'amount' => '50',
                'transaction_date' => '2026-08-29', 'payment_method' => null,
                'reference' => null, 'notes' => null, 'adjustment_reason' => null,
                'mutation_key' => (string) Str::uuid7(), 'edit_version' => null,
                'confirmed' => true,
            ],
        )->assertRedirect();

        return $this->tenant(
            $company,
            fn (): Document => Document::query()->findOrFail($invoice->id),
        );
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

    private function payment(Company $company): InvoiceTransaction
    {
        return $this->tenant(
            $company,
            fn (): InvoiceTransaction => InvoiceTransaction::query()->sole(),
        );
    }
}
