<?php

namespace App\Modules\Transactions\Data;

use App\Modules\Documents\Models\Document;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Transactions\Models\InvoiceTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

final readonly class LockedInvoiceTransactions
{
    /** @param Collection<int, InvoiceTransaction> $transactions */
    public function __construct(
        public Document $document,
        public Invoice $invoice,
        public Collection $transactions,
        public CarbonImmutable $companyLocalDate,
    ) {}
}
