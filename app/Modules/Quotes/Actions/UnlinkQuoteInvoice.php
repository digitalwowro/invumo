<?php

namespace App\Modules\Quotes\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Contracts\AuthorizesCompanyActions;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Delivery\Actions\LockDocumentDeliveryHistory;
use App\Modules\Delivery\Queries\DocumentPublicLinkHistory;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Quotes\Data\QuoteInvoiceUnlinkData;
use App\Modules\Quotes\Exceptions\QuoteInvoiceUnlinkException;
use App\Modules\Quotes\Models\Quote;
use App\Modules\Quotes\Models\QuoteInvoiceLink;
use App\Modules\Transactions\Models\InvoiceTransaction;
use Illuminate\Support\Facades\DB;

final readonly class UnlinkQuoteInvoice
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private RecordAuditEvent $recordAuditEvent,
        private DocumentPublicLinkHistory $publicLinkHistory,
        private LockDocumentDeliveryHistory $deliveryHistory,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        string $quoteId,
        string $invoiceId,
        QuoteInvoiceUnlinkData $data,
    ): void {
        $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): mixed => DB::connection(config('database.tenant_connection'))->transaction(
                fn (): bool => $this->unlink($company, $actor, $quoteId, $invoiceId, $data),
                3,
            ),
        );
    }

    private function unlink(
        Company $company,
        User $actor,
        string $quoteId,
        string $invoiceId,
        QuoteInvoiceUnlinkData $data,
    ): bool {
        $this->authorizer->authorize($actor, $company, CompanyAbility::UnlinkQuoteInvoice);

        if (! $data->confirmed) {
            throw QuoteInvoiceUnlinkException::confirmationRequired();
        }

        if ($data->reason === '' || mb_strlen($data->reason) > 500) {
            throw QuoteInvoiceUnlinkException::reasonInvalid();
        }

        $quoteDocument = Document::query()
            ->whereKey($quoteId)->where('kind', DocumentKind::Quote)
            ->lockForUpdate()->firstOrFail();
        Quote::query()->whereKey($quoteDocument->id)->lockForUpdate()->firstOrFail();
        $invoiceDocument = Document::query()
            ->whereKey($invoiceId)->where('kind', DocumentKind::Invoice)
            ->lockForUpdate()->firstOrFail();
        $invoice = Invoice::query()->whereKey($invoiceDocument->id)->lockForUpdate()->firstOrFail();
        $link = QuoteInvoiceLink::query()
            ->where('quote_id', $quoteDocument->id)
            ->where('invoice_id', $invoiceDocument->id)
            ->lockForUpdate()->firstOrFail();
        $transactions = InvoiceTransaction::query()
            ->where('invoice_id', $invoiceDocument->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $publicLinks = $this->publicLinkHistory->lock($invoiceDocument->id);
        $deliveries = $this->deliveryHistory->all($invoiceDocument->id);

        if ($invoice->lifecycle !== InvoiceLifecycle::Draft
            || $transactions->isNotEmpty()
            || $publicLinks->isNotEmpty()
            || $deliveries->isNotEmpty()) {
            throw QuoteInvoiceUnlinkException::unavailable();
        }

        $link->delete();
        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.quote.invoice_unlinked',
            targetType: 'Quote',
            targetId: $quoteDocument->id,
            reason: $data->reason,
            before: AuditPayload::fromAllowedFields([
                'invoice_id' => $invoiceDocument->id,
                'linked' => true,
            ], ['invoice_id', 'linked']),
            after: AuditPayload::fromAllowedFields([
                'invoice_id' => $invoiceDocument->id,
                'linked' => false,
            ], ['invoice_id', 'linked']),
        ));

        return true;
    }
}
