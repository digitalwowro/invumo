<?php

namespace App\Modules\Delivery\Queries;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Delivery\Data\OutwardDocument;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use App\Modules\Invoices\Data\ResolvedInvoiceState;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Quotes\Data\QuoteDisplayStatus;
use App\Modules\Quotes\Models\Quote;
use App\Modules\Transactions\Queries\InvoiceTransactionsForInvoice;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use RuntimeException;

final readonly class CurrentDocumentRepresentation
{
    public function __construct(
        private CompanyAbilityCheck $abilities,
        private OutwardDocumentProjection $projection,
        private InvoiceTransactionsForInvoice $transactions,
    ) {}

    public function forQuote(Company $company, User $actor, string $documentId): OutwardDocument
    {
        return $this->quote($company, $this->authorizedDocument(
            $company,
            $actor,
            $documentId,
            DocumentKind::Quote,
        ));
    }

    public function forInvoice(Company $company, User $actor, string $documentId): OutwardDocument
    {
        return $this->invoice($company, $this->authorizedDocument(
            $company,
            $actor,
            $documentId,
            DocumentKind::Invoice,
        ));
    }

    public function publicQuote(Company $company, Document $document): OutwardDocument
    {
        abort_unless($document->kind === DocumentKind::Quote, 404);

        return $this->quote($company, $document);
    }

    public function publicInvoice(Company $company, Document $document): OutwardDocument
    {
        abort_unless($document->kind === DocumentKind::Invoice, 404);

        return $this->invoice($company, $document);
    }

    private function quote(Company $company, Document $document): OutwardDocument
    {
        $quote = Quote::query()->whereKey($document->id)->firstOrFail();
        $settings = CompanySetting::query()->firstOrFail();
        $locale = $document->document_language ?? 'en';
        $status = QuoteDisplayStatus::resolve(
            $quote->lifecycle,
            $quote->valid_until,
            Date::now($settings->timezone ?? 'UTC')->toImmutable()->startOfDay(),
        );

        return $this->projection->build(
            $document,
            $this->translation('documents_outward.quote', $locale),
            $this->translation("documents_outward.statuses.{$status->value}", $locale),
            $quote->valid_until,
            null,
        );
    }

    private function invoice(Company $company, Document $document): OutwardDocument
    {
        $invoice = Invoice::query()->whereKey($document->id)->firstOrFail();
        $settings = CompanySetting::query()->firstOrFail();
        $locale = $document->document_language ?? 'en';
        $state = ResolvedInvoiceState::resolve(
            $invoice->lifecycle,
            $document->total,
            (string) $this->transactions->ledger($document->id)->netPaid(),
            $invoice->due_date,
            Date::now($settings->timezone ?? 'UTC')->toImmutable()->startOfDay(),
        );

        return $this->projection->build(
            $document,
            $this->translation('documents_outward.invoice', $locale),
            $this->translation("documents_outward.statuses.{$state->displayStatus->value}", $locale),
            null,
            $invoice->due_date,
        );
    }

    private function authorizedDocument(
        Company $company,
        User $actor,
        string $documentId,
        DocumentKind $kind,
    ): Document {
        if (! $this->abilities->allows($actor, $company, $kind->viewAbility())) {
            throw new AuthorizationException;
        }

        return Document::query()
            ->whereKey($documentId)
            ->where('kind', $kind)
            ->firstOrFail();
    }

    private function translation(string $key, string $locale): string
    {
        $translation = trans($key, locale: $locale);

        if (! is_string($translation)) {
            throw new RuntimeException("The outward document translation [{$key}] must be a string.");
        }

        return $translation;
    }
}
