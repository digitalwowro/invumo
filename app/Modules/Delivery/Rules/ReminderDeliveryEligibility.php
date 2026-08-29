<?php

namespace App\Modules\Delivery\Rules;

use App\Modules\Delivery\Data\ReminderInstanceStatus;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Delivery\Models\ReminderInstance;
use App\Modules\Documents\Models\Document;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Transactions\Data\InvoiceLedger;
use App\Modules\Transactions\Models\InvoiceTransaction;

final class ReminderDeliveryEligibility
{
    /** @param iterable<int, InvoiceTransaction> $transactions */
    public function allows(
        EmailDelivery $delivery,
        Document $document,
        ?Invoice $invoice,
        iterable $transactions,
    ): bool {
        $instance = $delivery->reminder_instance_id === null
            ? null
            : ReminderInstance::query()
                ->whereKey($delivery->reminder_instance_id)->lockForUpdate()->first();

        return $instance instanceof ReminderInstance
            && $instance->status === ReminderInstanceStatus::Claimed
            && $invoice?->lifecycle === InvoiceLifecycle::Issued
            && ! InvoiceLedger::fromTransactions($transactions)
                ->outstanding($document->total)->isZero();
    }
}
