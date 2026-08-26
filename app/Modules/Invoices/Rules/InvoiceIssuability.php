<?php

namespace App\Modules\Invoices\Rules;

use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentLine;
use App\Modules\Invoices\Exceptions\InvoiceLifecycleException;
use App\Modules\Invoices\Models\Invoice;
use Illuminate\Database\Eloquent\Collection;

final readonly class InvoiceIssuability
{
    /** @param Collection<int, DocumentLine> $lines */
    public function assert(Document $document, Invoice $invoice, Collection $lines): void
    {
        $complete = $lines->isNotEmpty()
            && $lines->every(fn ($line): bool => $line->final_line_total !== null);
        $required = $document->customer_id !== null
            && $document->rendered_number !== ''
            && $document->issue_date !== null
            && $invoice->due_date !== null
            && $document->currency_code !== null
            && $document->currency_precision !== null
            && $document->document_language !== null
            && $document->companySnapshot()->exists()
            && $document->customerSnapshot()->exists();

        if (! $complete || ! $required) {
            throw InvoiceLifecycleException::incomplete();
        }
    }
}
