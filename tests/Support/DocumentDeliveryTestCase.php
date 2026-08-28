<?php

namespace Tests\Support;

use App\Foundation\Delivery\EmailAttachmentMode;
use App\Foundation\Jobs\TenantJobExecution;
use App\Foundation\Tenancy\TenantContext;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\ResolveOutwardBrandTheme;
use App\Modules\Customers\Data\CustomerType;
use App\Modules\Customers\Data\DeliveryRecipientRole;
use App\Modules\Customers\Models\Customer;
use App\Modules\Delivery\Actions\CompleteDocumentDeliveryAttempt;
use App\Modules\Delivery\Actions\PrepareDocumentDeliveryArtifact;
use App\Modules\Delivery\Contracts\SendsProviderEmail;
use App\Modules\Delivery\Data\EmailDeliveryState;
use App\Modules\Delivery\Jobs\SendDocumentDelivery;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Delivery\Support\DocumentDeliveryQuota;
use App\Modules\Delivery\Support\DocumentEmailHtml;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentCustomerSnapshot;
use App\Modules\Documents\Models\DocumentDeliveryRecipient;
use App\Modules\Documents\Models\DocumentDeliverySetting;
use App\Modules\Documents\Models\DocumentLine;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Quotes\Data\QuoteLifecycle;
use App\Modules\Quotes\Models\Quote;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Str;

abstract class DocumentDeliveryTestCase extends PublicDocumentTestCase
{
    protected function executeDeliveryJob(
        string $companyId,
        string $deliveryId,
        SendsProviderEmail $provider,
        int $attempt = 1,
    ): void {
        $job = new SendDocumentDelivery($companyId, $deliveryId);
        $execution = app(TenantJobExecution::class);
        $execution->run(
            $job->identity,
            (string) Str::uuid(),
            $attempt,
            $job->tries,
            fn () => $job->handle(
                app(TenantContext::class),
                $execution,
                $provider,
                app(FilesystemManager::class),
                app(ResolveOutwardBrandTheme::class),
                app(DocumentEmailHtml::class),
                app(PrepareDocumentDeliveryArtifact::class),
                app(CompleteDocumentDeliveryAttempt::class),
                app(DocumentDeliveryQuota::class),
            ),
        );
    }

    protected function tearDown(): void
    {
        Company::query()->pluck('id')->each(fn (string $companyId) => app(TenantContext::class)
            ->runAsSystem($companyId, function (): void {
                EmailDelivery::query()
                    ->whereIn('dispatch_state', [
                        EmailDeliveryState::Queued,
                        EmailDeliveryState::Retrying,
                    ])
                    ->update([
                        'dispatch_state' => EmailDeliveryState::Rejected,
                        'failure_category' => 'test_cleanup',
                        'failure_summary' => 'Test cleanup.',
                        'failed_at' => now(),
                    ]);
                Invoice::query()->where('lifecycle', '!=', InvoiceLifecycle::Draft)
                    ->update(['lifecycle' => InvoiceLifecycle::Draft]);
                Quote::query()->where('lifecycle', '!=', QuoteLifecycle::Draft)
                    ->update(['lifecycle' => QuoteLifecycle::Draft]);
            }));
        parent::tearDown();
    }

    protected function completeQuote(Company $company, Document $document): Document
    {
        return $this->complete($company, $document, false);
    }

    protected function completeInvoice(Company $company, Document $document): Document
    {
        return $this->complete($company, $document, true);
    }

    /** @return array<string, mixed> */
    protected function deliveryPayload(
        Document $document,
        EmailAttachmentMode $mode = EmailAttachmentMode::SecureLinkOnly,
        ?string $key = null,
    ): array {
        return [
            'delivery_key' => $key ?? (string) Str::uuid7(),
            'edit_version' => $document->edit_version,
            'attachment_mode' => $mode->value,
            'subject' => 'Document {{public_url}}',
            'body' => 'Please review the document at {{public_url}}.',
            'button_label' => 'View securely',
            'signature' => 'Invumo Team',
            'confirmed_final_quote_state' => false,
            'recipients' => [[
                'role' => DeliveryRecipientRole::To->value,
                'name' => 'Ana Popescu',
                'email' => 'ANA@EXAMPLE.COM',
            ]],
        ];
    }

    private function complete(
        Company $company,
        Document $document,
        bool $invoice,
    ): Document {
        return $this->tenant($company, function () use ($document, $invoice): Document {
            $customer = Customer::query()->create([
                'type' => CustomerType::Company,
                'legal_name' => 'Delivery Customer SRL',
                'email' => 'ana@example.com',
            ]);
            $document->update([
                'customer_id' => $customer->id,
                'issue_date' => '2026-08-28',
                'currency_code' => 'RON',
                'currency_precision' => 2,
                'document_language' => 'en',
                'subtotal' => '100',
                'tax_total' => '0',
                'total' => '100',
            ]);
            DocumentCustomerSnapshot::query()->create([
                'document_id' => $document->id,
                'type' => CustomerType::Company,
                'legal_name' => 'Delivery Customer SRL',
                'email' => 'ana@example.com',
            ]);
            DocumentLine::query()->create([
                'document_id' => $document->id,
                'position' => 1,
                'description' => 'Consulting',
                'item_price' => '100',
                'quantity' => '1',
                'unit' => 'item',
                'period_unit' => 'NONE',
                'discount_percentage' => '0',
                'discount_amount' => '0',
                'tax_percentage' => '0',
                'items_subtotal' => '100',
                'items_total' => '100',
                'grand_subtotal' => '100',
                'tax_amount' => '0',
                'final_line_total' => '100',
            ]);
            DocumentDeliveryRecipient::query()->create([
                'document_id' => $document->id,
                'role' => DeliveryRecipientRole::To,
                'name' => 'Ana Popescu',
                'email' => 'ana@example.com',
                'display_order' => 1,
            ]);
            DocumentDeliverySetting::query()->where('document_id', $document->id)->update([
                'email_attachment_mode' => EmailAttachmentMode::SecureLinkOnly,
            ]);

            if ($invoice) {
                Invoice::query()->whereKey($document->id)->update([
                    'payment_term_days' => 30,
                    'due_date' => '2026-09-27',
                ]);
            } else {
                Quote::query()->whereKey($document->id)->update([
                    'validity_days' => 30,
                    'valid_until' => '2026-09-27',
                ]);
            }

            return $document->refresh();
        });
    }
}
