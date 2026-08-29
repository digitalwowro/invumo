<?php

namespace App\Modules\Delivery\Actions;

use App\Modules\Delivery\Data\EmailTemplateEvent;
use App\Modules\Delivery\Models\EmailDelivery;
use Illuminate\Database\Eloquent\Collection;

final class DetachPaymentReceivedDeliveries
{
    /** @param Collection<int, EmailDelivery> $lockedDeliveries */
    public function handle(Collection $lockedDeliveries, string $transactionId): void
    {
        $lockedDeliveries
            ->filter(fn (EmailDelivery $delivery): bool => $delivery->event_type === EmailTemplateEvent::PaymentReceived
                && $delivery->invoice_transaction_id === $transactionId)
            ->each->update(['invoice_transaction_id' => null]);
    }
}
