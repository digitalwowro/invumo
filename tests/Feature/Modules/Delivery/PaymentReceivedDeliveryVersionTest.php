<?php

namespace Tests\Feature\Modules\Delivery;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Delivery\Contracts\SendsProviderEmail;
use App\Modules\Delivery\Data\EmailDeliveryState;
use App\Modules\Delivery\Data\ProviderDelivery;
use App\Modules\Delivery\Data\ProviderDeliveryResult;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Documents\Models\Document;
use App\Modules\Invoices\Actions\IssueInvoice;
use App\Modules\Transactions\Models\InvoiceTransaction;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\DocumentDeliveryTestCase;

final class PaymentReceivedDeliveryVersionTest extends DocumentDeliveryTestCase
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

    public function test_payment_version_drift_blocks_submission_and_manual_retry(): void
    {
        [$owner, $company] = $this->company();
        $invoice = $this->issuedInvoice($company, $owner);
        $this->actingAs($owner)->post(
            route('invoice-transactions.store', [$company, $invoice]),
            $this->paymentPayload(),
        )->assertRedirect();
        $payment = $this->tenant(
            $company,
            fn (): InvoiceTransaction => InvoiceTransaction::query()->sole(),
        );
        Queue::fake();
        $this->post(
            route('invoice-transactions.payment-received.store', [$company, $invoice, $payment]),
            [
                'delivery_key' => (string) Str::uuid7(),
                'transaction_edit_version' => $payment->edit_version,
                'confirmed' => true,
            ],
        )->assertRedirect();
        $delivery = $this->tenant(
            $company,
            fn (): EmailDelivery => EmailDelivery::query()->sole(),
        );

        $this->tenant($company, fn () => InvoiceTransaction::query()
            ->whereKey($payment->id)
            ->update(['edit_version' => $payment->edit_version + 1]));

        $provider = new VersionDriftRecordingProvider;
        $this->executeDeliveryJob($company->id, $delivery->id, $provider);

        $this->assertSame([], $provider->deliveries);
        $this->tenant($company, function (): void {
            $delivery = EmailDelivery::query()->sole();
            $this->assertSame(EmailDeliveryState::Rejected, $delivery->dispatch_state);
            $this->assertSame('payment_received_no_longer_eligible', $delivery->failure_category);
        });

        Queue::fake();
        $this->post(
            route('invoices.deliveries.retry', [$company, $invoice, $delivery]),
            ['confirmed' => true],
        )->assertSessionHasErrors('delivery');
        Queue::assertNothingPushed();
    }

    public function test_validated_payment_correction_blocks_retry_of_frozen_receipt_content(): void
    {
        [$owner, $company] = $this->company();
        $invoice = $this->issuedInvoice($company, $owner);
        $this->actingAs($owner)->post(
            route('invoice-transactions.store', [$company, $invoice]),
            $this->paymentPayload(),
        );
        $payment = $this->tenant(
            $company,
            fn (): InvoiceTransaction => InvoiceTransaction::query()->sole(),
        );
        Queue::fake();
        $this->post(
            route('invoice-transactions.payment-received.store', [$company, $invoice, $payment]),
            [
                'delivery_key' => (string) Str::uuid7(),
                'transaction_edit_version' => $payment->edit_version,
                'confirmed' => true,
            ],
        );
        $delivery = $this->tenant(
            $company,
            fn (): EmailDelivery => EmailDelivery::query()->sole(),
        );
        $this->tenant($company, fn () => $delivery->update([
            'dispatch_state' => EmailDeliveryState::Rejected,
            'failure_category' => 'provider_rejected',
            'failure_summary' => 'Rejected before correction.',
            'failed_at' => now(),
        ]));

        $this->patch(
            route('invoice-transactions.update', [$company, $invoice, $payment]),
            $this->paymentPayload('30', $payment->edit_version),
        )->assertRedirect();

        Queue::fake();
        $this->post(
            route('invoices.deliveries.retry', [$company, $invoice, $delivery]),
            ['confirmed' => true],
        )->assertSessionHasErrors('delivery');
        Queue::assertNothingPushed();
        $this->tenant($company, function (): void {
            $this->assertSame(2, InvoiceTransaction::query()->sole()->edit_version);
            $this->assertSame(1, EmailDelivery::query()->sole()->invoice_transaction_edit_version);
            $this->assertStringContainsString('50.00', (string) EmailDelivery::query()->sole()->body);
        });
    }

    private function issuedInvoice(Company $company, User $owner): Document
    {
        $invoice = $this->completeInvoice($company, $this->invoice($company, $owner));

        return app(IssueInvoice::class)->handle(
            $company,
            $owner,
            $invoice->id,
            $invoice->edit_version,
        );
    }

    /** @return array<string, mixed> */
    private function paymentPayload(string $amount = '50', ?int $editVersion = null): array
    {
        return [
            'kind' => 'PAYMENT',
            'adjustment_direction' => null,
            'amount' => $amount,
            'transaction_date' => '2026-08-29',
            'payment_method' => 'Bank transfer',
            'reference' => null,
            'notes' => null,
            'adjustment_reason' => null,
            'mutation_key' => (string) Str::uuid7(),
            'edit_version' => $editVersion,
            'confirmed' => true,
        ];
    }
}

final class VersionDriftRecordingProvider implements SendsProviderEmail
{
    /** @var list<ProviderDelivery> */
    public array $deliveries = [];

    public function send(ProviderDelivery $delivery): ProviderDeliveryResult
    {
        $this->deliveries[] = $delivery;

        return ProviderDeliveryResult::accepted('unexpected-version-drift-send');
    }
}
