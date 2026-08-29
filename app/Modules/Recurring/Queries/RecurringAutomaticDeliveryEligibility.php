<?php

namespace App\Modules\Recurring\Queries;

use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Documents\Models\Document;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Recurring\Data\RecurringDeliverySource;

final class RecurringAutomaticDeliveryEligibility
{
    public function __construct(private readonly RecurringInvoiceSource $sources) {}

    public function lockForDelivery(
        EmailDelivery $delivery,
    ): ?RecurringDeliverySource {
        if (! $delivery->recurring_automatic || $delivery->document_id === null) {
            return null;
        }

        return $this->lockForInvoice($delivery->document_id);
    }

    public function lockForInvoice(string $invoiceId): ?RecurringDeliverySource
    {
        return $this->sources->lock($invoiceId);
    }

    public function allowsCurrent(EmailDelivery $delivery, Document $document): bool
    {
        if (! $delivery->recurring_automatic) {
            return true;
        }

        $source = $this->sources->current($document->id);
        $invoice = Invoice::query()->whereKey($document->id)->first();

        return $this->allows(
            $source,
            $delivery,
            $document,
            $invoice,
        );
    }

    public function allows(
        ?RecurringDeliverySource $source,
        EmailDelivery $delivery,
        Document $document,
        ?Invoice $invoice,
    ): bool {
        if (! $source instanceof RecurringDeliverySource
            || ! $delivery->recurring_automatic
            || $delivery->document_id !== $source->occurrence->invoice_id
            || $document->id !== $source->occurrence->invoice_id
            || $delivery->document_edit_version !== $document->edit_version
            || ! $source->template->automatic_email_enabled
            || ! $source->occurrence->automatic_email_requested
            || $source->occurrence->automatic_delivery_suppression_reason !== null
            || ! $invoice instanceof Invoice
            || $invoice->lifecycle !== InvoiceLifecycle::Issued) {
            return false;
        }

        if (! $source->occurrence->currency_inherited) {
            return true;
        }

        return ! $source->template->currency_review_required
            && $source->template->last_confirmed_delivery_currency !== null
            && $source->template->last_confirmed_delivery_currency === $document->currency_code;
    }
}
