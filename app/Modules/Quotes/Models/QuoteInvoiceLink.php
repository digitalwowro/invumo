<?php

namespace App\Modules\Quotes\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentDeliverySetting;
use App\Modules\Invoices\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $quote_id
 * @property string $invoice_id
 * @property string|null $copied_by_user_id
 * @property string $creation_key
 * @property CarbonImmutable $copied_at
 * @property-read Document $invoiceDocument
 * @property-read Invoice $invoice
 * @property-read DocumentDeliverySetting $invoiceDelivery
 */
#[Fillable([
    'quote_id', 'invoice_id', 'copied_by_user_id', 'creation_key', 'copied_at',
])]
final class QuoteInvoiceLink extends TenantOwnedModel
{
    /** @return BelongsTo<Document, $this> */
    public function invoiceDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'invoice_id');
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id', 'document_id');
    }

    /** @return BelongsTo<DocumentDeliverySetting, $this> */
    public function invoiceDelivery(): BelongsTo
    {
        return $this->belongsTo(DocumentDeliverySetting::class, 'invoice_id', 'document_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['copied_at' => 'immutable_datetime'];
    }
}
