<?php

namespace App\Modules\Delivery\Rules;

use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Transactions\Data\InvoiceTransactionKind;
use App\Modules\Transactions\Models\InvoiceTransaction;

final class PaymentReceivedDeliveryEligibility
{
    /** @param iterable<int, InvoiceTransaction> $transactions */
    public function allows(
        EmailDelivery $delivery,
        ?Invoice $invoice,
        iterable $transactions,
    ): bool {
        if ($delivery->invoice_transaction_id === null
            || $delivery->invoice_transaction_edit_version === null
            || $invoice?->lifecycle !== InvoiceLifecycle::Issued) {
            return false;
        }

        foreach ($transactions as $transaction) {
            if ($transaction->id === $delivery->invoice_transaction_id) {
                return $transaction->kind === InvoiceTransactionKind::Payment
                    && $transaction->edit_version === $delivery->invoice_transaction_edit_version;
            }
        }

        return false;
    }
}
