<?php

namespace App\Modules\Transactions\Actions;

use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Transactions\Data\LockedInvoiceTransactions;
use App\Modules\Transactions\Models\InvoiceTransaction;
use Illuminate\Support\Facades\Date;

final class LockInvoiceTransactionAggregate
{
    public function handle(string $documentId): LockedInvoiceTransactions
    {
        $settings = CompanySetting::query()->orderBy('id')->lockForUpdate()->firstOrFail();
        $document = Document::query()
            ->whereKey($documentId)
            ->where('kind', DocumentKind::Invoice)
            ->lockForUpdate()
            ->firstOrFail();
        $invoice = Invoice::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
        $transactions = InvoiceTransaction::query()
            ->where('invoice_id', $document->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        return new LockedInvoiceTransactions(
            $document,
            $invoice,
            $transactions,
            Date::now($settings->timezone ?? 'UTC')->toImmutable()->startOfDay(),
        );
    }
}
