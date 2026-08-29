<?php

namespace App\Modules\Delivery\Actions;

use App\Modules\Delivery\Data\EmailDeliveryState;
use App\Modules\Delivery\Data\EmailTemplateEvent;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Delivery\Models\EmailDeliveryAttempt;
use Illuminate\Database\Eloquent\Collection;

final class LockDocumentDeliveryHistory
{
    /** @return Collection<int, EmailDelivery> */
    public function all(string $documentId): Collection
    {
        return EmailDelivery::query()
            ->where('document_id', $documentId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    public function hasPending(string $documentId): bool
    {
        return EmailDelivery::query()
            ->where('document_id', $documentId)
            ->whereIn('dispatch_state', [
                EmailDeliveryState::Queued,
                EmailDeliveryState::Retrying,
            ])
            ->orderBy('id')
            ->lockForUpdate()
            ->first() instanceof EmailDelivery;
    }

    public function hasPendingDirect(string $documentId): bool
    {
        return EmailDelivery::query()
            ->where('document_id', $documentId)
            ->where('event_type', '!=', EmailTemplateEvent::PaymentReminder)
            ->whereIn('dispatch_state', [
                EmailDeliveryState::Queued,
                EmailDeliveryState::Retrying,
            ])
            ->orderBy('id')
            ->lockForUpdate()
            ->first() instanceof EmailDelivery;
    }

    /** @param Collection<int, EmailDelivery> $deliveries */
    public function hasSubmissionInFlight(Collection $deliveries): bool
    {
        if ($deliveries->isEmpty()) {
            return false;
        }

        return EmailDeliveryAttempt::query()
            ->whereIn('delivery_id', $deliveries->pluck('id'))
            ->where('state', 'PENDING')
            ->orderBy('id')
            ->lockForUpdate()
            ->first() instanceof EmailDeliveryAttempt;
    }
}
